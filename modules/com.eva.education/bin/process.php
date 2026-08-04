<?php

declare(strict_types=1);

use Eva\Infrastructure\Database\Connection;
use Eva\ModuleRuntime\RuntimeFactory;
use EvaModule\Education\EducationModule;
use EvaModule\Education\Interpreter\LearningInterpreter;

$root = dirname(__DIR__, 3);
$container = require $root . '/bootstrap/app.php';
require $root . '/modules/runtime/bootstrap.php';

if (!in_array('--live', $argv, true)) {
    fwrite(STDERR, "Use --live para autorizar chamadas reais ao provedor de IA.\n");
    exit(2);
}

$limit = 10;

foreach ($argv as $argument) {
    if (preg_match('/^--limit=(\d+)$/', $argument, $matches) === 1) {
        $limit = max(1, min(100, (int) $matches[1]));
    }
}

$factory = new RuntimeFactory($root, Connection::create($container['database']), $container['ai']);
$manifest = $factory->registry()->find('com.eva.education');
$context = $factory->context($manifest);
$module = $factory->registry()->load($manifest);

if (!$module instanceof EducationModule) {
    throw new RuntimeException('O pacote educacional instalado é incompatível.');
}

$module->install($context);
$result = (new LearningInterpreter())->processPending($context, $limit);
echo json_encode($result, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES) . PHP_EOL;
exit($result['failed'] > 0 ? 1 : 0);
