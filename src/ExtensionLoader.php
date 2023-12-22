<?php

declare(strict_types=1);

namespace Janvernieuwe\DevBranchCheck;

use GrumPHP\Extension\ExtensionInterface;

final class ExtensionLoader implements ExtensionInterface
{
    public function imports(): iterable
    {
        yield __DIR__ . '/../services.yaml';
    }
}
