import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import { createRequire, Module } from "node:module";
import { test } from "node:test";
import { fileURLToPath } from "node:url";
import ts from "typescript";

const base = "../../resources/js/pages/administrators/system-management/enrollment-policy/components/workflow-canvas/";
function load(name) {
    const url = new URL(`${base}${name}.ts`, import.meta.url);
    const filename = fileURLToPath(url);
    const { outputText } = ts.transpileModule(readFileSync(url, "utf8"), {
        fileName: filename,
        compilerOptions: { module: ts.ModuleKind.CommonJS, target: ts.ScriptTarget.ES2022 },
    });
    const mod = new Module(filename);
    const require = createRequire(url);
    mod.require = (id) => (id === "./workflow-model" ? model : require(id));
    mod._compile(outputText, filename);
    return mod.exports;
}
const model = load("workflow-model");
const { workflowToGraph } = load("workflow-graph-adapter");
const { computeAutoLayout } = load("layout");
const route = (key, to, fallback = true) => ({ key, label: key, to, fallback, conditions: [] });
const action = { key: "notify", handler: "enrollment.notify", configuration: { notification: "status_update" } };
const fixture = () => [
    {
        key: "review",
        label: "Review",
        entry: true,
        terminal: false,
        status: "Pending",
        actions: [action],
        transitions: [route("approve", "completed")],
    },
    { key: "completed", label: "Completed", entry: false, terminal: true, status: "Enrolled", outcome: "completed", actions: [], transitions: [] },
];

test("keeps branches ahead of fallback and changing fallback never creates two defaults", () => {
    const transitions = [route("default", "completed")];
    const added = model.appendTransition(transitions, route("conditional", "other", false));
    assert.deepEqual(
        added.map((item) => item.key),
        ["conditional", "default"],
    );
    const replaced = model.replaceTransition(added, { ...added[0], fallback: true });
    assert.deepEqual(
        replaced.map((item) => item.key),
        ["default", "conditional"],
    );
    assert.equal(replaced.filter((item) => item.fallback).length, 1);
    assert.equal(transitions.length, 1);
});

test("mints distinct action keys without changing original configuration", () => {
    const actions = model.appendActions([action], [action, action]);
    assert.deepEqual(
        actions.map((item) => item.key),
        ["notify", "notify_2", "notify_3"],
    );
    assert.equal(action.key, "notify");
});

test("does not change entry by array position or remove last outcome", () => {
    const steps = fixture();
    steps.unshift({ ...steps[0], key: "middle", entry: false, transitions: [route("next", "completed")] });
    const next = model.removeStep(steps, "middle");
    assert.equal(next[0].entry, true);
    assert.equal(next[0].key, "review");
    assert.equal(model.removeStep(next, "review"), next);
    assert.equal(model.removeStep(next, "completed"), next);
});

test("detects disconnected components, cycles, invalid fallback order and duplicate payload keys", () => {
    const steps = fixture();
    steps[0].actions = [action, action];
    steps[0].transitions.push(route("branch", "cycle", false));
    steps.push({ ...steps[0], key: "cycle", entry: false, actions: [], transitions: [route("loop", "cycle")] });
    steps.push({ ...steps[1], key: "unreachable" });
    const messages = model
        .validateWorkflowGraph(steps)
        .issues.map((item) => item.message)
        .join("\n");
    assert.match(messages, /Otherwise must be last/);
    assert.match(messages, /cycles are not supported/);
    assert.match(messages, /unreachable/);
    assert.match(messages, /unique keys/);
    assert.equal(model.validateWorkflowGraph(fixture()).issues.length, 0);
});

test("rejects invalid drawn connections before altering workflow", () => {
    const steps = fixture();
    steps.splice(1, 0, { ...steps[0], key: "second", entry: false });
    assert.equal(model.canConnectSteps(steps, "review", "second"), true);
    assert.equal(model.canConnectSteps(steps, "second", "review"), false);
    assert.equal(model.canConnectSteps(steps, "completed", "second"), false);
    assert.equal(model.canConnectSteps(steps, "review", "missing"), false);
    assert.equal(model.canConnectSteps(steps, "review", "completed"), false);
});

test("reports historical missing action keys instead of crashing the canvas", () => {
    const steps = fixture();
    steps[0].actions = [{ handler: "enrollment.notify", configuration: {} }];
    assert.match(model.validateWorkflowGraph(steps).issues[0].message, /unique keys/);
});

test("renders one explicit submission trigger and keeps ordered actions inside their stage", () => {
    const steps = fixture();
    const noop = () => {};
    const graph = workflowToGraph({
        steps,
        roles: [],
        actions: {},
        rules: {},
        direction: "LR",
        entryRules: [{ key: "channels", handler: "availability.channel", configuration: { allowed: ["administrator", "api"] } }],
        onEditStep: noop,
        onDeleteStep: noop,
        onEditTransition: noop,
        onDeleteTransition: noop,
        onEditAction: noop,
        onDeleteAction: noop,
        onReorderAction: noop,
    });
    assert.equal(graph.nodes.length, 3);
    assert.deepEqual(graph.nodes[0].data.channels, ["administrator", "api"]);
    assert.deepEqual(graph.nodes[1].data.step.actions, [action]);
    assert.equal(graph.edges[0].target, "review");
    assert.equal(graph.edges[1].source, "review");
    assert.equal(graph.edges[1].target, "completed");
    for (const direction of ["LR", "TB"]) {
        const layout = computeAutoLayout(graph.nodes, graph.edges, direction);
        for (const node of layout.nodes) {
            assert.ok(Number.isFinite(node.position.x) && Number.isFinite(node.position.y));
        }
    }
});
