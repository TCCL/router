<?php

/**
 * RouterRESTHandling.php
 *
 * tccl/router
 */

namespace TCCL\Router;

/**
 * RouterRESTHandling
 *
 * Provides functionality for building REST API routers that use JSON for state
 * representation. A router that uses this trait will handle handler return
 * values as JSON payloads and will automatically write them to the output
 * stream.
 */
trait RouterRESTHandling {
    /**
     * Writes a JSON-encoded object to the output stream.
     *
     * @param array $payload
     *  The contents of the object.
     */
    public function writeJson(array $payload) {
        $this->contentType = Router::CONTENT_JSON;
        $this->flush();

        echo json_encode($payload) . PHP_EOL;
    }

    /**
     * Writes an empty JSON object to the output stream.
     */
    public function writeEmptyJsonObject() {
        $this->writeJson([]);
    }

    /**
     * Issues an HTTP 204 "No Content" status code.
     */
    public function noContent() {
        $this->statusCode = 204;
        $this->flush();
    }

    /**
     * Overrides Router::resultHandler().
     */
    protected function resultHandler($result) {
        if (!isset($result)) {
            $this->noContent();
        }
        else if (is_array($result)) {
            $this->writeJson($result);
        }
    }
}