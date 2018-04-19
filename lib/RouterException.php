<?php

/**
 * RouterException.php
 *
 * @package tccl/router
 */

namespace TCCL\Router;

use Exception;

/**
 * RouterException
 *
 * Represents an exception thrown from a route's request handler.
 */
class RouterException extends Exception {
    private $statusCode;
    private $error;
    private $reason;

    /**
     * Creates a new WebconfException.
     *
     * @param int $statusCode
     *  The HTTP status code
     * @param string $error
     *  Short error description
     * @param string $reason
     *  Long-form error reason
     */
    public function __construct($statusCode,$error,$reason) {
        $this->statusCode = $statusCode;
        $this->error = $error;
        $this->reason = $reason;
    }

    /**
     * Gets the HTTP status code.
     *
     * @return int
     */
    public function getStatusCode() {
        return $this->statusCode;
    }

    /**
     * Gets the short error messsage.
     *
     * @return string
     */
    public function getError() {
        return $this->error;
    }

    /**
     * Gets the long error message (i.e. reason).
     *
     * @return string
     */
    public function getReason() {
        return $this->reason;
    }
}
