<?php

/**
 * RouterRESTHandling.php
 *
 * @package tccl\router
 */

namespace TCCL\Router;

/**
 * Provides functionality for building REST API routers that use JSON for state
 * representation.
 *
 * A router that uses this trait will handle handler return values as JSON
 * payloads and will automatically write them to the output stream.
 */
trait RouterRESTHandling {
    /**
     * Writes a JSON-encoded object to the output stream.
     *
     * @param mixed $payload
     *  A json-encodable value.
     * @param int $statusCode
     *  The HTTP status code to assign to the response.
     */
    public function writeJson($payload,$statusCode = 200) : void {
        if (!headers_sent()) {
            $this->statusCode = $statusCode;
            $this->contentType = Router::CONTENT_JSON;
            $this->flush();
        }

        echo json_encode($payload);
    }

    /**
     * Writes an empty JSON object to the output stream.
     *
     * @param int $statusCode
     *  The HTTP status code to assign to the response.
     */
    public function writeEmptyJsonObject(int $statusCode = 200) : void {
        if (!headers_sent()) {
            $this->statusCode = $statusCode;
            $this->contentType = Router::CONTENT_JSON;
            $this->flush();
        }

        echo '{}';
    }

    /**
     * Issues an HTTP 204 "No Content" status code.
     */
    public function noContent() : void {
        if (headers_sent()) {
            return;
        }

        $this->statusCode = 204;
        $this->flush();
    }

    /**
     * Overrides Router::resultHandler().
     */
    protected function resultHandler($result) : void {
        if (!isset($result)) {
            $this->noContent();
        }
        else if (is_array($result) || $result instanceof \JsonSerializable) {
            $this->writeJson($result);
        }
    }
}
