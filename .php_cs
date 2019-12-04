<?php

return PhpCsFixer\Config::create()
    ->setRules(['@Symfony'])
    ->setFinder(
        PhpCsFixer\Finder::create()
            ->exclude(['vendor'])
            ->in(__DIR__)
    )
;
