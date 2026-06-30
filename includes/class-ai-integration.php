<?php
if (!defined('ABSPATH')) exit;

class AZ_AI_Integration {

    private $api_key;
    private $base_url;
    private $model;

    public function __construct() {
        $this->api_key  = get_option('az_openai_api_key', '');
        $this->base_url = rtrim(get_option('az_openai_base_url', 'https://api.openai.com/v1'), '/');
        $this->model    = get_option('az_openai_model', 'gpt-4o-mini');
    }

    public function is_configured() {
        return !empty($this->api_key);
    }

    private function normalize_nvidia_url() {
        $parsed = parse_url($this->base_url);
        $host = $parsed['host'] ?? '';
        if (strpos($host, 'nvidia') !== false || strpos($host, 'nvcf') !== false) {
            if (strpos($host, 'integrate.api.nvidia.com') !== false) {
                $path = $parsed['path'] ?? '';
                if ($path !== '/v1' && $path !== '/v1/') {
                    return 'https://integrate.api.nvidia.com/v1';
                }
            }
        }
        return $this->base_url;
    }

    public function fetch_models() {
        if (!$this->is_configured()) {
            return ['error' => 'API key not configured'];
        }

        $base = $this->normalize_nvidia_url();
        $models = [];
        $parsed = parse_url($base);
        $host = $parsed['host'] ?? '';
        $is_nvidia = strpos($host, 'nvidia') !== false || strpos($host, 'nvcf') !== false;

        $urls_to_try = [
            $base . '/models',
        ];

        foreach ($urls_to_try as $url) {
            $response = wp_remote_get($url, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->api_key,
                    'Content-Type'  => 'application/json',
                ],
                'timeout' => 15,
            ]);

            if (is_wp_error($response)) {
                continue;
            }

            $status = wp_remote_retrieve_response_code($response);
            if ($status !== 200) {
                continue;
            }

            $body = json_decode(wp_remote_retrieve_body($response), true);
            if (!$body || !is_array($body)) {
                continue;
            }

            if (isset($body['data']) && is_array($body['data'])) {
                foreach ($body['data'] as $m) {
                    if (isset($m['id'])) {
                        $models[] = $m['id'];
                    }
                }
            } elseif (isset($body['models']) && is_array($body['models'])) {
                foreach ($body['models'] as $m) {
                    if (is_string($m)) {
                        $models[] = $m;
                    } elseif (isset($m['id'])) {
                        $models[] = $m['id'];
                    }
                }
            }

            if (!empty($models)) {
                break;
            }
        }

        if (empty($models) && $is_nvidia) {
            return [
                'success' => true,
                'models'  => [
                    'mistralai/mistral-7b-instruct-v0.3', 'meta/llama-3.1-8b-instruct',
                    'mistralai/mistral-large', 'google/gemma-2-27b-it',
                    'nvidia/nemotron-4-340b-instruct', 'meta/codellama-70b',
                    'google/gemma-2-9b-it', 'mistralai/mixtral-8x7b-instruct-v0.1',
                ],
                'note'    => 'Auto-detect failed. Common NVIDIA models listed. Use "Custom Model" to enter any model name from build.nvidia.com.',
            ];
        }

        if (empty($models)) {
            return [
                'success' => true,
                'models'  => [],
                'note'    => 'Could not auto-detect models. Your provider may not support the /models endpoint. Use "Custom Model" option below to enter the model name manually.',
            ];
        }

        sort($models);

        return ['success' => true, 'models' => array_values(array_unique($models))];
    }

    public function analyze($issues_array) {
        if (!$this->is_configured()) {
            return ['error' => 'API key not configured'];
        }

        $site_name = get_bloginfo('name');
        $site_url = get_site_url();

        $issues_text = '';
        foreach ($issues_array as $issue) {
            $issues_text .= "- [{$issue['severity']}] {$issue['type']}: {$issue['title']}\n  {$issue['description']}\n\n";
        }

        $prompt = "You are a WordPress optimization expert analyzing {$site_name} ({$site_url}). ";
        $prompt .= "Analyze these issues and provide a prioritized action plan with time estimates:\n\n";
        $prompt .= $issues_text;
        $prompt .= "Provide:\n1. Priority order of fixes\n2. Time estimate for each\n3. Expected impact\n4. Additional recommendations";

        return $this->call_api($prompt);
    }

    public function generate_meta_description($content) {
        if (!$this->is_configured()) {
            return ['error' => 'API key not configured'];
        }
        $prompt = "Write an SEO meta description (max 160 characters) for this content:\n\n{$content}\n\nMeta description:";
        return $this->call_api($prompt);
    }

    public function generate_alt_text($image_context) {
        if (!$this->is_configured()) {
            return ['error' => 'API key not configured'];
        }
        $prompt = "Write alt text (5-8 words) for this website image: {$image_context}\n\nAlt text:";
        return $this->call_api($prompt);
    }

    private function call_api($prompt) {
        $base = $this->normalize_nvidia_url();
        $parsed = parse_url($base);
        $host = $parsed['host'] ?? '';

        $endpoints = [$base . '/chat/completions'];

        if (strpos($host, 'nvidia') !== false || strpos($host, 'nvcf') !== false) {
            $endpoints[] = 'https://integrate.api.nvidia.com/v1/chat/completions';
        }

        $payload = [
            'model'       => $this->model,
            'messages'    => [
                ['role' => 'user', 'content' => $prompt],
            ],
            'max_tokens'  => 1000,
            'temperature' => 0.7,
        ];

        $last_error = '';
        foreach ($endpoints as $url) {
            $response = wp_remote_post($url, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->api_key,
                    'Content-Type'  => 'application/json',
                ],
                'body'    => json_encode($payload),
                'timeout' => 45,
            ]);

            if (is_wp_error($response)) {
                $last_error = 'API request failed: ' . $response->get_error_message();
                continue;
            }

            $status_code = wp_remote_retrieve_response_code($response);
            $response_body = wp_remote_retrieve_body($response);
            $data = json_decode($response_body, true);

            if ($status_code === 404) {
                $last_error = 'API endpoint not found at ' . $url . '. Check your Base URL (should end with /v1 for most providers).';
                continue;
            }

            if ($status_code !== 200) {
                $error_msg = isset($data['error']['message']) ? $data['error']['message'] : 'Unknown error';
                $last_error = "API error ({$status_code}): {$error_msg}";

                if ($status_code === 401) {
                    $last_error .= '. Check your API key.';
                } elseif ($status_code === 400 && strpos($error_msg, 'model') !== false) {
                    $last_error .= '. The model "' . $this->model . '" may not exist. Try "Fetch Models" to see available models.';
                }
                continue;
            }

            if (isset($data['choices'][0]['message']['content'])) {
                return ['success' => true, 'content' => trim($data['choices'][0]['message']['content'])];
            }

            return ['error' => 'Unexpected API response format'];
        }

        AZ_Logger::log("AI API call failed: {$last_error}", 'ERROR');
        return ['error' => $last_error];
    }
}
