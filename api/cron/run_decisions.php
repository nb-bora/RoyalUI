<?php
declare(strict_types=1);

/**
 * Tâche planifiée — moteur de décision
 * Windows : schtasks /create /tn "PharmaRoyalDecisions" /tr "php C:\xampp\htdocs\RoyalUI\api\cron\run_decisions.php" /sc hourly
 * Linux   : 0 * * * * php /path/RoyalUI/api/cron/run_decisions.php
 */
require_once dirname(__DIR__) . '/helpers.php';
require_once dirname(__DIR__) . '/services/DecisionEngine.php';

$result = DecisionEngine::run();
echo date('Y-m-d H:i:s') . ' DecisionEngine: ' . json_encode($result) . "\n";
