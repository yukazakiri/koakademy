keys == ["docs", "extensions", "frontend", "pest", "pr-title", "scope", "static", "workflow-security"] and
.scope.result == "success" and
(
  if $release_only == "true" and $event_name == "pull_request" then
    .docs.result == "skipped" and
    .extensions.result == "skipped" and
    .frontend.result == "skipped" and
    .pest.result == "skipped" and
    .static.result == "skipped" and
    .["workflow-security"].result == "skipped"
  else
    .docs.result == "success" and
    .extensions.result == "success" and
    .frontend.result == "success" and
    .pest.result == "success" and
    .static.result == "success" and
    .["workflow-security"].result == "success"
  end
) and
(
  if $event_name == "pull_request" then
    .["pr-title"].result == "success"
  else
    .["pr-title"].result == "skipped"
  end
)
