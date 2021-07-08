<?php

/**
 * PayloadVerifyException.php
 *
 * tccl/router
 */

namespace TCCL\Router;

class PayloadVerifyException extends RouterException {
    private $failedVariable;

    private $failedFormat;

    public function __construct($failedVariable,$failedFormat) {
        parent::__construct(400);

        $this->failedVariable = $failedVariable;
        $this->failedFormat = $failedFormat;
    }

    public function printDebug() {
        $variable = var_export($this->failedVariable,true);
        $format = var_export($this->failedFormat,true);

        error_log("The payload '$variable' did not match the format $format");
    }
}
