<?php

namespace Braceyourself\Compose\Exceptions;

use RuntimeException;
use Illuminate\Support\Stringable;
use Illuminate\Contracts\Process\ProcessResult;

class ComposeProcessFailedException extends RuntimeException
{
    public $result;

    public function __construct(ProcessResult $result)
    {
        $this->result = $result;

        $error = str($result->command())->between('"','"')
            ->after('| ')
            ->replace(' -f -', '')
            ->when($result->output(), fn(Stringable $s, $output) => $s->append(sprintf("\n\nOutput:\n================\n%s", $output)))
            ->when($result->errorOutput(), fn(Stringable $s, $output) => $s->append(sprintf("\n\nError Output:\n================\n%s", $output)))
        ;

        parent::__construct($error, $result->exitCode() ?? 1);
    }

}