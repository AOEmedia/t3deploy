<?php
return [
    'controllers' => [
        \AOE\T3Deploy\Command\T3DeployCommandController::class
    ],
    'runLevels' => [
        'crawlerCommand' => \Helhum\Typo3Console\Core\Booting\RunLevel::LEVEL_MINIMAL
    ],
    'bootingSteps' => []
];
