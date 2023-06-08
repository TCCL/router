<?php

/**
 * RouterException.php
 *
 * @package tccl\router
 */

namespace TCCL\Router;

use Exception;

/**
 * Represents an exception thrown from a route's request handler.
 */
class RouterException extends Exception {
    private $statusCode;
    private $error;
    private $reason;

    /**
     * Creates a new RouterException.
     *
     * @param int $statusCode
     *  The HTTP status code
     * @param string $error
     *  Short error description
     * @param string $reason
     *  Long-form error reason
     */
    public function __construct($statusCode,$error = null,$reason = null) {
        $this->statusCode = $statusCode;
        $this->error = isset($error) ? $error : self::getDefaultError($statusCode);
        $this->reason = isset($reason) ? $reason : self::getDefaultReason($statusCode);
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

    private static function getDefaultError($statusCode) {
        switch ($statusCode) {
        case 400:
            return 'Bad Request';
        case 401:
            return 'Not Authorized';
        case 403:
            return 'Permission Denied';
        case 404:
            return 'Not Found';
        case 422:
            return 'Validation Failed';
        case 501:
            return 'Not Implemented';
        case 502:
            return 'Bad Gateway';
        case 503:
            return 'Service Unavailable';
        case 504:
            return 'Gateway Timeout';
        }

        return 'Server Error';
    }

    private static function getDefaultReason($statusCode) {
        switch ($statusCode) {
        case 400:
            return 'The request is malformed and could not be processed.';
        case 401:
            return 'The request requires user authorization.';
        case 403:
            return 'The server refused to process your request.';
        case 404:
            return 'The resource indicated by the request was not found on this server.';
        case 422:
            return 'The request failed validation and could not be processed.';
        case 501:
            return 'The server does not support the requested functionality.';
        case 502:
            return 'The server could not process a response from the upstream server.';
        case 503:
            return 'The server cannot process your request at this time.';
        case 504:
            return 'The server cannot contact the upstream server at this time.';
        }

        return 'An error occurred and the server could not process your request.';
    }
}
