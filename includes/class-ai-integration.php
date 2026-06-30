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

    public function fetch_models() {
        if (!$this->is_configured()) {
            return ['error' => 'API key not configured'];
        }

        $url = $this->base_url . '/models';

        $response = wp_remote_get($url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type'  => 'application/json',
            ],
            'timeout' => 15,
        ]);

        if (is_wp_error($response)) {
            return ['error' => 'Failed to fetch models: ' . $response->get_error_message()];
        }

        $status = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if ($status !== 200) {
            $msg = $body['error']['message'] ?? 'HTTP ' . $status;
            return ['error' => 'API error: ' . $msg];
        }

        $models = [];
        if (isset($body['data']) && is_array($body['data'])) {
            foreach ($body['data'] as $m) {
                if (isset($m['id']) && strpos($m['id'], 'gpt') !== false) {
                    $models[] = $m['id'];
                }
            }
            if (empty($models)) {
                foreach ($body['data'] as $m) {
                    if (isset($m['id'])) $models[] = $m['id'];
                }
            }
            sort($models);
        }

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
        $url = $this->base_url . '/chat/completions';

        $body = json_encode([
            'model'       => $this->model,
            'messages'    => [
                ['role' => 'user', 'content' => $prompt],
            ],
            'max_tokens'  => 1000,
            'temperature' => 0.7,
        ]);

        $response = wp_remote_post($url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type'  => 'application/json',
            ],
            'body'    => $body,
            'timeout' => 30,
        ]);

        if (is_wp_error($response)) {
            return ['error' => 'API request failed: ' . $response->get_error_message()];
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        $data = json_decode($response_body, true);

        if ($status_code !== 200) {
            $error_msg = isset($data['error']['message']) ? $data['error']['message'] : 'Unknown error';
            AZ_Logger::log("AI API error: {$error_msg}", 'ERROR');
            return ['error' => "API error ({$status_code}): {$error_msg}"];
        }

        if (isset($data['choices'][0]['message']['content'])) {
            return ['success' => true, 'content' => trim($data['choices'][0]['message']['content'])];
        }

        return ['error' => 'Unexpected API response format'];
    }
}
