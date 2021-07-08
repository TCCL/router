<?php

/**
 * PayloadVerifyException.php
 *
 * tccl/router
 */

namespace TCCL\Router;

/**
 * Provides a RouterException that is thrown when PayloadVerify fails to verify
 * a payload.
 */
class PayloadVerifyException extends RouterException {
    /**
     * @var mixed
     */
    private $failedVariable;

    /**
     * @var string
     */
    private $failedFormat;

    public function __construct($failedVariable,$failedFormat) {
        parent::__construct(400);

        $this->failedVariable = $failedVariable;
        $this->failedFormat = $failedFormat;
    }

    /**
     * Gets the variable value that failed to verify.
     *
     * @return mixed
     */
    public function getVariable() {
        return $this->failedVariable;
    }

    /**
     * Gets the format that failed to match.
     *
     * @return string
     */
    public function getFormat() {
        return $this->failedFormat;
    }

    /**
     * Prints a message to stderr that is useful when debugging a failed payload
     * verification.
     */
    public function printDebug() {
        $variable = var_export($this->failedVariable,true);
        $format = var_export($this->failedFormat,true);

        error_log("The payload '$variable' did not match the format $format");
    }
}
