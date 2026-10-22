keys == ["docs", "pr-title", "scope", "validate", "workflow-security"] and
.scope.result == "success" and
(
  if $release_only == "true" and $event_name == "pull_request" then
    .validate.result == "skipped" and
    .["workflow-security"].result == "skipped" and
    .docs.result == "skipped"
  else
    .validate.result == "success" and
    .["workflow-security"].result == "success" and
    .docs.result == "success"
  end
) and
(
  if $event_name == "pull_request" then
    .["pr-title"].result == "success"
  else
    .["pr-title"].result == "skipped"
  end
)
