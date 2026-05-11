<?php
require 'includes/application_top.php';

$pluginRoot = DIR_FS_CATALOG . 'zc_plugins/zen-ai-assist/v1.0.0/';

require_once $pluginRoot . 'catalog/includes/classes/ZenAiAssistPathHelper.php';
require_once $pluginRoot . 'catalog/includes/classes/ZenAiAssistJsonStorage.php';
require_once $pluginRoot . 'catalog/includes/classes/ZenAiAssistDocSourceRegistry.php';

$pathHelper = new ZenAiAssistPathHelper($pluginRoot, DIR_FS_CATALOG);
$storage = new ZenAiAssistJsonStorage();
$sources = ZenAiAssistDocSourceRegistry::all();
$docsIndex = $storage->readJsonFile($pathHelper->docsIndexPath());
$repoIndex = $storage->readJsonFile($pathHelper->repoIndexPath());
$cacheFiles = $pathHelper->listJsonFiles($pathHelper->docsCacheDirectory());

$docsChunkCount = isset($docsIndex['chunks']) && is_array($docsIndex['chunks']) ? count($docsIndex['chunks']) : 0;
$repoRecordCount = isset($repoIndex['records']) && is_array($repoIndex['records']) ? count($repoIndex['records']) : 0;
?>
<!doctype html>
<html <?php echo HTML_PARAMS; ?>>
  <head>
    <?php require DIR_WS_INCLUDES . 'admin_html_head.php'; ?>
  </head>
  <body>
    <?php require DIR_WS_INCLUDES . 'header.php'; ?>

    <div class="container-fluid">
      <h1><?php echo HEADING_TITLE; ?></h1>
      <p><?php echo TEXT_ZEN_AI_ASSIST_INTRO; ?></p>

      <div class="row">
        <div class="col-md-6">
          <div class="panel panel-default">
            <div class="panel-heading"><strong><?php echo TEXT_ZEN_AI_ASSIST_STATUS; ?></strong></div>
            <div class="panel-body">
              <p><?php echo TEXT_ZEN_AI_ASSIST_DOC_CACHE; ?>: <?php echo (int)count($cacheFiles); ?></p>
              <p><?php echo TEXT_ZEN_AI_ASSIST_DOC_CHUNKS; ?>: <?php echo (int)$docsChunkCount; ?></p>
              <p><?php echo TEXT_ZEN_AI_ASSIST_REPO_RECORDS; ?>: <?php echo (int)$repoRecordCount; ?></p>
            </div>
          </div>
        </div>
        <div class="col-md-6">
          <div class="panel panel-default">
            <div class="panel-heading"><strong><?php echo TEXT_ZEN_AI_ASSIST_DOC_SOURCES; ?></strong></div>
            <div class="panel-body">
              <ul>
                <?php foreach ($sources as $source) { ?>
                  <li><?php echo htmlspecialchars($source['url'], ENT_COMPAT, CHARSET, false); ?></li>
                <?php } ?>
              </ul>
            </div>
          </div>
        </div>
      </div>

      <div class="panel panel-default">
        <div class="panel-heading"><strong><?php echo TEXT_ZEN_AI_ASSIST_USAGE; ?></strong></div>
        <div class="panel-body">
          <p><code><?php echo TEXT_ZEN_AI_ASSIST_USAGE_FETCH; ?></code></p>
          <p><code><?php echo TEXT_ZEN_AI_ASSIST_USAGE_BUILD; ?></code></p>
          <p><code><?php echo TEXT_ZEN_AI_ASSIST_USAGE_SEARCH; ?></code></p>
          <p><?php echo TEXT_ZEN_AI_ASSIST_MCP_NOTE; ?></p>
        </div>
      </div>
    </div>

    <?php require DIR_WS_INCLUDES . 'footer.php'; ?>
  </body>
</html>
<?php
require DIR_WS_INCLUDES . 'application_bottom.php';
