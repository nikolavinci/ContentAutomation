<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class CA_Cluster_Engine {
    public function __construct() {
        add_action('ca_process_clustering_queue', [$this, 'process_clustering']);
    }
    
    public function process_clustering() {
        global $wpdb;
        $wpdb->insert($wpdb->prefix . 'ca_logs', ['action' => 'clustering', 'level' => 'INFO', 'message' => "Starting AI clustering queue..."]);
        
        $urls = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}ca_urls WHERE status IN ('ready_for_clustering', 'ready_for_ai') OR (status = 'ai_failed' AND (cluster_id IS NULL OR cluster_id = 0)) LIMIT 20");
        
        if (empty($urls)) {
            $wpdb->insert($wpdb->prefix . 'ca_logs', ['action' => 'clustering', 'level' => 'INFO', 'message' => "No articles ready for clustering."]);
            return;
        }
        
        if (count($urls) == 1) {
            $wpdb->update($wpdb->prefix . 'ca_urls', ['status' => 'clustered', 'cluster_id' => $urls[0]->id], ['id' => $urls[0]->id]);
            $wpdb->insert($wpdb->prefix . 'ca_logs', ['action' => 'clustering', 'level' => 'INFO', 'message' => "Only 1 article found. Skipping AI cluster and assigning cluster ID " . $urls[0]->id]);
            return;
        }
        
        $payload_items = [];
        foreach ($urls as $u) {
            $response = wp_remote_get($u->url, ['timeout' => 15, 'user-agent' => 'Mozilla/5.0']);
            $title = $u->url;
            if (!is_wp_error($response) && preg_match('/<title>(.*?)<\/title>/is', wp_remote_retrieve_body($response), $matches)) {
                $title = wp_strip_all_tags($matches[1]);
            }
            $payload_items[] = "ID: {$u->id} | Title: {$title}";
        }
        
        $prompt = "You are an AI News clustering bot. Group these news articles into semantic clusters based on their exact topic/event. Group them ONLY if they are reporting on the exact same news event. If an article is unique, put it in a cluster by itself. Return ONLY a valid JSON array of arrays of IDs. Example output: [[1, 2], [3], [4, 5]].\n\nArticles:\n" . implode("\n", $payload_items);
        
        $ai_response = $this->call_provider($prompt);
        if (!$ai_response) {
            $wpdb->insert($wpdb->prefix . 'ca_logs', ['action' => 'clustering', 'level' => 'ERROR', 'message' => "Clustering aborted due to AI Provider error. (Check keys/credits)"]);
            return;
        }
        
        $clusters = json_decode($ai_response, true);
        if (!is_array($clusters)) {
            $wpdb->insert($wpdb->prefix . 'ca_logs', ['action' => 'clustering', 'level' => 'ERROR', 'message' => "AI returned invalid JSON for clustering."]);
            return;
        }
        
        foreach ($clusters as $cluster_group) {
            if (empty($cluster_group) || !is_array($cluster_group)) continue;
            $primary_id = intval($cluster_group[0]);
            foreach ($cluster_group as $id) {
                $id = intval($id);
                $wpdb->update($wpdb->prefix . 'ca_urls', ['status' => 'clustered', 'cluster_id' => $primary_id], ['id' => $id]);
            }
        }
        
        $wpdb->insert($wpdb->prefix . 'ca_logs', ['action' => 'clustering', 'level' => 'SUCCESS', 'message' => "Successfully clustered " . count($urls) . " articles into " . count($clusters) . " groups."]);
    }
    
    private function call_provider($prompt) {
        $provider = get_option('ca_ai_provider', 'gemini');
        
        if (in_array($provider, ['openai', 'groq', 'deepseek', 'qwen'])) {
            $key_map = [
                'openai' => 'ca_openai_key',
                'groq' => 'ca_groq_key',
                'deepseek' => 'ca_deepseek_key',
                'qwen' => 'ca_qwen_key'
            ];
            $url_map = [
                'openai' => 'https://api.openai.com/v1/chat/completions',
                'groq' => 'https://api.groq.com/openai/v1/chat/completions',
                'deepseek' => 'https://api.deepseek.com/chat/completions',
                'qwen' => 'https://dashscope-intl.aliyuncs.com/compatible-mode/v1/chat/completions'
            ];
            $model_map_option = [
                'openai' => 'ca_openai_model',
                'groq' => 'ca_groq_model',
                'deepseek' => 'ca_deepseek_model',
                'qwen' => 'ca_qwen_model'
            ];
            
            $key = get_option($key_map[$provider]);
            if (empty($key)) {
                $this->log_error("Clustering Error: " . strtoupper($provider) . " API Key is missing.");
                return false;
            }
            
            $url = $url_map[$provider];
            
            // Get custom model name, fallback to defaults if somehow empty
            $model = get_option($model_map_option[$provider]);
            if (empty($model)) {
                if ($provider == 'openai') $model = 'gpt-4o-mini';
                if ($provider == 'groq') $model = 'llama3-70b-8192';
                if ($provider == 'deepseek') $model = 'deepseek-chat';
                if ($provider == 'qwen') $model = 'qwen-turbo';
            }
            
            $payload = ['model' => $model, 'messages' => [['role' => 'user', 'content' => $prompt]], 'temperature' => 0.2];
            
            if ($provider == 'openai' || $provider == 'deepseek') {
                $payload['response_format'] = ['type' => 'json_object'];
            }
            
            $response = wp_remote_post($url, [
                'headers' => ['Authorization' => 'Bearer ' . $key, 'Content-Type' => 'application/json'],
                'body' => json_encode($payload),
                'timeout' => 45
            ]);
            
            if (is_wp_error($response)) {
                $this->log_error("Clustering Error (" . strtoupper($provider) . "): " . $response->get_error_message());
                return false;
            }
            
            $body = json_decode(wp_remote_retrieve_body($response), true);
            if (isset($body['error'])) {
                $this->log_error("Clustering API Error (" . strtoupper($provider) . "): " . json_encode($body['error']));
                return false;
            }
            
            if (isset($body['usage'])) {
                $this->track_usage($provider, $body['usage']['prompt_tokens'], $body['usage']['completion_tokens']);
            }
            
            $content = $body['choices'][0]['message']['content'] ?? false;
            if ($content) {
                $content = preg_replace('/```json\s*/', '', $content);
                $content = preg_replace('/```/', '', $content);
            }
            return $content;
            
        } elseif ($provider == 'gemini') {
            $key = get_option('ca_gemini_key');
            if (empty($key)) {
                $this->log_error("Clustering Error: Gemini API Key is missing.");
                return false;
            }
            
            $model = get_option('ca_gemini_model', 'gemini-3.6-flash');
            if (empty($model)) $model = 'gemini-3.6-flash';
            
            $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$key}";
            
            $response = wp_remote_post($url, [
                'headers' => ['Content-Type' => 'application/json'],
                'body' => json_encode(['contents' => [['parts' => [['text' => $prompt]]]]]),
                'timeout' => 45
            ]);
            
            if (is_wp_error($response)) {
                $this->log_error("Clustering Error (Gemini): " . $response->get_error_message());
                return false;
            }
            
            $body = json_decode(wp_remote_retrieve_body($response), true);
            if (isset($body['error'])) {
                $this->log_error("Clustering API Error (Gemini): " . $body['error']['message']);
                return false;
            }
            
            if (isset($body['usageMetadata'])) {
                $this->track_usage('gemini', $body['usageMetadata']['promptTokenCount'], $body['usageMetadata']['candidatesTokenCount']);
            }
            
            $text = $body['candidates'][0]['content']['parts'][0]['text'] ?? false;
            if ($text) {
                $text = preg_replace('/```json\s*/', '', $text);
                $text = preg_replace('/```/', '', $text);
                return trim($text);
            }
            return false;
        }
        return false;
    }
    
    private function track_usage($provider, $prompt_tokens, $completion_tokens) {
        $cost = 0;
        if ($provider == 'openai') {
            $cost = ($prompt_tokens / 1000000 * 0.150) + ($completion_tokens / 1000000 * 0.600);
        } elseif ($provider == 'gemini') {
            $cost = ($prompt_tokens / 1000000 * 0.075) + ($completion_tokens / 1000000 * 0.300);
        } elseif ($provider == 'groq') {
            $cost = ($prompt_tokens / 1000000 * 0.59) + ($completion_tokens / 1000000 * 0.79);
        } elseif ($provider == 'deepseek') {
            $cost = ($prompt_tokens / 1000000 * 0.14) + ($completion_tokens / 1000000 * 0.28);
        } elseif ($provider == 'qwen') {
            $cost = ($prompt_tokens / 1000000 * 0.003) + ($completion_tokens / 1000000 * 0.006);
        }
        
        $total_tokens = get_option('ca_total_tokens', 0) + $prompt_tokens + $completion_tokens;
        $total_cost = get_option('ca_total_cost', 0) + $cost;
        
        update_option('ca_total_tokens', $total_tokens);
        update_option('ca_total_cost', $total_cost);
    }
    
    private function log_error($message) {
        global $wpdb;
        $wpdb->insert($wpdb->prefix . 'ca_logs', ['action' => 'clustering', 'level' => 'ERROR', 'message' => $message]);
    }
}