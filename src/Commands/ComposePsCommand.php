<?php

namespace Braceyourself\Compose\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Yaml\Yaml;
use Illuminate\Support\Facades\Process;
use Braceyourself\Compose\Concerns\CreatesComposeServices;
use function Laravel\Prompts\confirm;

class ComposePsCommand extends ComposeStatusCommand
{
    protected $signature = 'compose:ps';
}
