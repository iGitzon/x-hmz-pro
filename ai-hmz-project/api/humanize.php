<?php
// humanize.php - Secure Gateway
// This file receives requests from the plugin, validates the license,
// calls the Google Gemini API, and returns the result.

header('Content-Type: application/json');

// ===================================================================================
// 1. CONFIGURATION (YOUR SECRETS GO HERE)
// ===================================================================================
$envato_personal_token = 'FC3VlEWVG1y2yD55555qz2uz7oW8TmUE';    // Your secret Envato API token.
$google_gemini_api_key = 'AIzaSyDTW08YGYfTX6fwV8y2IcDxgx71O7UbNXI';    // Your secret Google Gemini API key.

// ===================================================================================
// 2. DATA VALIDATION
// ===================================================================================
$license_key   = isset($_POST['license_key']) ? trim($_POST['license_key']) : '';
$item_id       = isset($_POST['item_id']) ? trim($_POST['item_id']) : '';
$original_text = isset($_POST['original_text']) ? $_POST['original_text'] : '';
$selected_tone = isset($_POST['selected_tone']) ? $_POST['selected_tone'] : 'Casual';
$selected_mode = isset($_POST['selected_mode']) ? $_POST['selected_mode'] : 'balanced';

// All requests MUST have a license key and item ID.
if (empty($license_key) || empty($item_id)) {
    echo json_encode(['status' => 'error', 'message' => 'Missing license key or item ID.']);
    exit;
}

// ===================================================================================
// 3. LICENSE VERIFICATION
// ===================================================================================
$my_test_key = 'AHP-LOCAL-TEST-KEY-12345';

if ($license_key === $my_test_key) {
    // It's the test key. Allow the script to continue.
} else {
    // If it's NOT the test key, proceed with the normal Envato check
    $api_url = 'https://api.envato.com/v3/market/author/sale?code=' . urlencode($license_key);
    $ch = curl_init($api_url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $envato_personal_token],
        CURLOPT_USERAGENT      => 'AiHProKey2'
    ]);
    $response  = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code !== 200) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid License. Could not verify with Envato.']);
        exit;
    }

    $data = json_decode($response, true);
    if (!isset($data['item']['id']) || $data['item']['id'] != $item_id) {
        echo json_encode(['status' => 'error', 'message' => 'License key is not valid for this item.']);
        exit;
    }
}

// If we reach here, the license key has been verified.

// ===================================================================================
// 4. HANDLE REQUEST TYPE (ACTIVATION PING vs. USAGE)
// ===================================================================================

// If original_text is empty, this is just an activation check.
// Since the license was verified above, we can return success now.
if (empty($original_text)) {
    echo json_encode(['status' => 'success', 'message' => 'License verified successfully.']);
    exit;
}

// If we reach here, it means the license is valid AND we have text to humanize.

// ===================================================================================
// 5. PROMPT CREATION & GOOGLE GEMINI API CALL
// ===================================================================================

function create_gemini_prompt($selected_tone, $selected_mode, $original_text) {
    // (The full prompt creation logic remains here, no changes needed)
    // ...
    // ...
    $starters = [
        'Casual' => [ "Here's the deal.", "Basically, it's this.", "So, what's next?", "Think about it.", "Let's be real.", "Bottom line?", "No doubt about it.", "In a nutshell...", "It's simple, really.", "The real story is...", "Let's unpack this.", "When you look at it...", "Here's the thing.", "The main point is...", "Let's cut to it.", "Long story short...", "Here's the scoop.", "Let's face it.", "Check this out.", "At the end of the day...", "Okay, so picture this." ],
        'Professional' => [ "Regarding the issue...", "The primary goal is...", "For clarification...", "The data indicates...", "Moving forward...", "From a business standpoint...", "To summarize briefly...", "Objectively speaking...", "The analysis shows...", "The key finding is...", "In review...", "The strategic outlook is...", "For the record...", "The core issue is...", "To be precise...", "To provide context...", "The official position is...", "In accordance with...", "The executive summary is...", "For strategic alignment...", "Key performance indicators show..." ],
        'Persuasive' => [ "Consider the possibility.", "The choice is clear.", "Imagine the impact.", "Here is the truth.", "You deserve this.", "It's time for change.", "The solution is here.", "Why should you wait?", "This changes everything.", "The opportunity is now.", "Think of the results.", "A better way exists.", "The argument is simple.", "Here's what matters.", "This is your moment.", "The future is here.", "Don't miss out.", "This is your advantage.", "Unlock your potential.", "The clear choice is...", "A powerful new way..." ],
        'Creative' => [ "Beyond the surface...", "A new chapter begins.", "The story starts here.", "Imagine a world where...", "Through a different lens...", "The scene is set.", "A spark of an idea.", "Let's explore this.", "What if it were...?", "The illusion shatters.", "A thread of thought.", "From the ashes came...", "The silence spoke.", "An echo of truth.", "The stage is yours.", "A shadow falls.", "The stage is empty.", "From a single seed...", "The canvas is blank.", "A crack in the mirror.", "Listen to the silence." ],
        'Academic' => [ "The data suggests...", "The thesis is clear.", "Upon initial analysis...", "Evidence indicates that...", "Within this framework...", "The research shows...", "A critical review finds...", "To contextualize this...", "The argument follows that...", "The study reveals...", "Hence, it is argued...", "The paper posits...", "The findings imply...", "The premise is...", "A key distinction is...", "The research corroborates...", "The methodology dictates...", "A paradigm shift is...", "The literature review confirms...", "Quantitatively speaking...", "The hypothesis holds that..." ]
    ];
    $starter_pool = $starters[$selected_tone] ?? $starters['Professional'];
    $random_starter = $starter_pool[array_rand($starter_pool)];
    $mode_instructions = [
        'precise' => "**Execution Mode: PRECISE.** Adhere closely to the original text's meaning and structure. Make minimal, surgical changes to improve flow and word choice. The goal is maximum fidelity with subtle humanization.",
        'balanced' => "**Execution Mode: BALANCED.** This is the default. Rewrite for a natural, human flow while preserving the core message. You have moderate freedom to restructure sentences for better readability and impact.",
        'creative' => "**Execution Mode: CREATIVE.** You have significant freedom to be transformative. Feel free to dramatically restructure sentences, employ more vivid and metaphorical language, and re-order points if it enhances the narrative. The final meaning must be the same, but the artistic expression can be entirely new."
    ];
    $selected_mode_instruction = $mode_instructions[$selected_mode] ?? $mode_instructions['balanced'];
    $prompt_parts = [];
    $prompt_parts[] = "You are 'Janus', a world-class editor. Your mission is to rewrite AI-generated content into prose that is indistinguishable from a top-tier human writer, specifically to achieve a high human score on AI detectors.";
    $prompt_parts[] = "**Core Mandate: Erase All Traces of AI Origin.** Your output must have maximum perplexity (linguistic unpredictability) and burstiness (sentence structure variation).";
    $prompt_parts[] = "---";
    $prompt_parts[] = "**PRIMARY DIRECTIVES (Harmonize these two rules):**";
    $prompt_parts[] = $selected_mode_instruction;
    $prompt_parts[] = "**Tone to Embody: {$selected_tone}.** The entire rewrite must reflect this tone consistently.";
    $prompt_parts[] = "---";
    $prompt_parts[] = "**CRITICAL REWRITE PRINCIPLES (Non-negotiable):** 1.  **Dynamic Opening:** Do NOT use a generic starting sentence. Use this phrase for inspiration to craft a unique opening: \"{$random_starter}\"
2.  **Aggressive Burstiness:** Mix very short, impactful sentences (3-5 words) with longer, more elaborate ones. The rhythm must be unpredictable.
3.  **Sophisticated Vocabulary:** Replace common AI words with more precise and evocative synonyms. Avoid repetition.
4.  **Demolish AI-isms:** Obliterate formal, robotic transition words ('Furthermore', 'Moreover', 'In conclusion', 'Additionally', 'It is important to note'). Transitions must be implicit and natural.
5.  **Inject a Point of View:** Rewrite from a more confident, descriptive, or opinionated perspective (within the bounds of the chosen tone). Aim for a Flesch-Kincaid reading score between Grade 8 and 10 for accessibility.";
    $prompt_parts[] = "**Absolute Constraints:** 1.  **100% Meaning Preservation:** The core message and facts are sacred.
2.  **No New Information:** Do not add external facts.
3.  **Final Output Only:** Your response must ONLY be the rewritten text. No preamble, no titles, no apologies, no markdown.";
    $prompt_parts[] = "---
**Original Text to Rewrite:** {$original_text}
---";
    return implode("\n\n", $prompt_parts);
}

$prompt = create_gemini_prompt($selected_tone, $selected_mode, $original_text);

$gemini_api_url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key=' . $google_gemini_api_key;
$gemini_payload = json_encode(['contents' => [['role' => 'user', 'parts' => [['text' => $prompt]]]]]);

$ch_gemini = curl_init($gemini_api_url);
curl_setopt_array($ch_gemini, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $gemini_payload,
    CURLOPT_TIMEOUT        => 60
]);
$gemini_response = curl_exec($ch_gemini);
$gemini_http_code = curl_getinfo($ch_gemini, CURLINFO_HTTP_CODE);
curl_close($ch_gemini);

if ($gemini_http_code !== 200) {
    echo json_encode(['status' => 'error', 'message' => 'Failed to connect to the humanization API.']);
    exit;
}

$gemini_data = json_decode($gemini_response, true);
if (isset($gemini_data['candidates'][0]['content']['parts'][0]['text'])) {
    echo json_encode(['status' => 'success', 'humanized_text' => $gemini_data['candidates'][0]['content']['parts'][0]['text']]);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Could not extract text from the API response.']);
}