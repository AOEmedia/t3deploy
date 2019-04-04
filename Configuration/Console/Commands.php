<?php
return [
    'controllers' => [
        \AOE\T3Deploy\Command\T3DeployCommandController::class
    ],
    'runLevels' => [
        'T3DeployCommand' => \Helhum\Typo3Console\Core\Booting\RunLevel::LEVEL_MINIMAL
    ],
    'bootingSteps' => []
];
