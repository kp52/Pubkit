<?php
    include 'chunkie.class.inc.php';
    $tpl = $modx->getChunk('newsItem.tpl'); ;
    $phx = new prePHx($tpl);
    $page = $modx->getTemplateVarOutput('*', '737',0);
    $phx->setPlaceholders($page);
    $parsed = $phx->output();
    $modx->setPlaceholder('phx-modified-content', $parsed);
?>