<?php

declare(strict_types=1);

/**
 * Copyright OpenSearch Contributors
 * SPDX-License-Identifier: Apache-2.0
 *
 * OpenSearch PHP client
 *
 * @link      https://github.com/opensearch-project/opensearch-php/
 * @copyright Copyright (c) Elasticsearch B.V (https://www.elastic.co)
 * @license   http://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
 * @license   https://www.gnu.org/licenses/lgpl-2.1.html GNU Lesser General Public License, Version 2.1
 *
 * Licensed to Elasticsearch B.V under one or more agreements.
 * Elasticsearch B.V licenses this file to you under the Apache 2.0 License or
 * the GNU Lesser General Public License, Version 2.1, at your option.
 * See the LICENSE file in the project root for more information.
 */

namespace OpenSearch\Endpoints;

use OpenSearch\EndpointInterface;
use OpenSearch\Exception\UnexpectedValueException;
use OpenSearch\Serializers\SerializerInterface;

use function array_filter;

abstract class AbstractEndpoint implements EndpointInterface
{
    /**
     * @var array
     */
    protected $params = [];

    /**
     * @var string|null
     */
    protected $index = null;

    /**
     * @var string|int|null
     */
    protected $id = null;

    /**
     * @var string|null
     */
    protected $method = null;

    /**
     * @var string|array|null
     */
    protected $body = null;

    /**
     * @var array
     */
    private $options = [];

    /**
     * @var SerializerInterface
     */
    protected $serializer;

    /**
     * @return string[]
     */
    abstract public function getParamWhitelist(): array;

    /**
     * @return string
     */
    abstract public function getURI(): string;

    /**
     * @return string
     */
    abstract public function getMethod(): string;

    /**
     * Set the parameters for this endpoint
     *
     * @param mixed[] $params Array of parameters
     * @return $this
     */
    public function setParams(array $params): static
    {
        $this->extractOptions($params);
        $this->checkUserParams($params);
        $params = $this->convertCustom($params);
        $this->params = $this->convertArraysToStrings($params);

        $this->checkForDeprecations();

        return $this;
    }

    public function getParams(): array
    {
        return $this->params;
    }

    public function getOptions(): array
    {
        return $this->options;
    }

    public function getIndex(): ?string
    {
        return $this->index;
    }

    /**
     * @param string|string[]|null $index
     *
     * @return $this
     */
    public function setIndex($index): static
    {
        if ($index === null) {
            return $this;
        }

        if (is_array($index) === true) {
            $index = array_filter($index);
            $index = array_map('trim', $index);
            $index = implode(",", $index);
        }

        $this->index = urlencode($index);

        return $this;
    }

    public function setId(int|string|null $docID): static
    {
        if ($docID === null) {
            return $this;
        }

        if (is_int($docID)) {
            $docID = (string)$docID;
        }

        $this->id = urlencode($docID);

        return $this;
    }

    public function getBody(): string|array|null
    {
        return $this->body;
    }

    public function setBody(string|iterable|null $body): static
    {
        $this->body = $body;

        return $this;
    }

    protected function getOptionalURI(string $endpoint): string
    {
        $uri = [];
        $uri[] = $this->getOptionalIndex();
        $uri[] = $endpoint;
        $uri = array_filter($uri);

        return '/' . implode('/', $uri);
    }

    private function getOptionalIndex(): string
    {
        if (isset($this->index) === true) {
            return $this->index;
        } else {
            return '_all';
        }
    }

    /**
     * @param array<string, mixed> $params
     *
     * @throws UnexpectedValueException
     */
    private function checkUserParams(array $params)
    {
        if (empty($params)) {
            return; //no params, just return.
        }

        $whitelist = array_merge(
            $this->getParamWhitelist(),
            ['pretty', 'human', 'error_trace', 'source', 'filter_path', 'opaqueId']
        );

        $invalid = array_diff(array_keys($params), $whitelist);
        if (count($invalid) > 0) {
            sort($invalid);
            sort($whitelist);
            throw new UnexpectedValueException(
                sprintf(
                    (count($invalid) > 1 ? '"%s" are not valid parameters.' : '"%s" is not a valid parameter.') . ' Allowed parameters are "%s"',
                    implode('", "', $invalid),
                    implode('", "', $whitelist)
                )
            );
        }
    }

    /**
     * @param array<string, mixed> $params Note: this is passed by-reference!
     */
    private function extractOptions(&$params)
    {
        // Extract out client options, then start transforming
        if (isset($params['client']) === true) {
            // Check if the opaqueId is populated and add the header
            if (isset($params['client']['opaqueId']) === true) {
                if (isset($params['client']['headers']) === false) {
                    $params['client']['headers'] = [];
                }
                $params['client']['headers']['x-opaque-id'] = [trim($params['client']['opaqueId'])];
                unset($params['client']['opaqueId']);
            }

            $this->options['client'] = $params['client'];
            unset($params['client']);
        }
        $ignore = isset($this->options['client']['ignore']) ? $this->options['client']['ignore'] : null;
        if (isset($ignore) === true) {
            if (is_string($ignore)) {
                $this->options['client']['ignore'] = explode(",", $ignore);
            } elseif (is_array($ignore)) {
                $this->options['client']['ignore'] = $ignore;
            } else {
                $this->options['client']['ignore'] = [$ignore];
            }
        }
    }

    /**
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    private function convertCustom(array $params): array
    {
        if (isset($params['custom']) === true) {
            foreach ($params['custom'] as $k => $v) {
                $params[$k] = $v;
            }
            unset($params['custom']);
        }

        return $params;
    }

    private function convertArraysToStrings(array $params): array
    {
        foreach ($params as $key => &$value) {
            if (!($key === 'client' || $key == 'custom') && is_array($value) === true) {
                if ($this->isNestedArray($value) !== true) {
                    $value = implode(",", $value);
                }
            }
        }

        return $params;
    }

    private function isNestedArray(array $a): bool
    {
        foreach ($a as $v) {
            if (is_array($v)) {
                return true;
            }
        }

        return false;
    }

    /**
     * This function returns all param deprecations also optional with a replacement field
     *
     * @return array<string, string|null>
     */
    protected function getParamDeprecation(): array
    {
        return [];
    }

    private function checkForDeprecations(): void
    {
        $deprecations = $this->getParamDeprecation();

        if ($deprecations === []) {
            return;
        }

        $keys = array_keys($this->params);

        foreach ($keys as $key) {
            if (array_key_exists($key, $deprecations)) {
                $val = $deprecations[$key];

                $msg = sprintf('The parameter "%s" is deprecated and will be removed without replacement in the next major version', $key);

                if ($val) {
                    $msg = sprintf('The parameter "%s" is deprecated and will be replaced with parameter "%s" in the next major version', $key, $val);
                }

                trigger_error($msg, E_USER_DEPRECATED);
            }
        }
    }
}
