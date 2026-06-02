<?php

require_once __DIR__ . '/../config.php';

function build_user_context_line(array $user): string
{
    $name = trim((string)($user['name'] ?? ''));
    $ageRange = trim((string)($user['age_range'] ?? ''));
    $gender = trim((string)($user['gender'] ?? ''));
    $country = trim((string)($user['country'] ?? ''));   
    $profile = $user['communication_profile'] ?? [];
    $style = '';
    if (is_array($profile)) {
        $style = trim((string)($profile['preset'] ?? ''));
    } else {
        $style = trim((string)$profile);
    }
    if ($style === '') {
        $style = trim((string)($user['communication_style'] ?? ''));
    }

    $parts = [];
    
    if ($name !== '') {
        $parts[] = $name;
    }
    
    $traits = [];
    
    if ($ageRange !== '') {
        $traits[] = strtolower($ageRange);
    }
    
    if ($gender !== '') {
        $traits[] = strtolower($gender);
    }
    
    if ($country !== '') {
        $traits[] = 'from ' . $country;
    }
    
    if (!empty($traits)) {
        $parts[] = 'is ' . implode(' ', $traits);
    }
    
    if ($style !== '') {
        $parts[] = 'with a ' . $style . ' communication style';
    }
    
    return trim(implode(' ', $parts), " \t\n\r\0\x0B.");
}


function build_conversation_context_block(array $context): string
{
    $user = $context['user'] ?? [];
    $sender = $context['sender'] ?? [];
    
    $userName = trim((string)($user['name'] ?? ''));
    $senderName = trim((string)($sender['name'] ?? ''));
    
    $lines = [];
    
    $userLine = build_user_context_line($user);
    if ($userLine !== '') {
        $lines[] = '- ' . $userLine . '.';
    }
    
    if ($userName !== '' && $senderName !== '') {
        $lines[] = '- Respond for ' . $userName . ' to ' . $senderName . '.';
    } elseif ($userName !== '') {
        $lines[] = '- Respond for ' . $userName . '.';
    }
    
    if ($senderName !== '') {
        $lines[] = '- Never reply from ' . $senderName . '\'s perspective.';
    }
    
    if (empty($lines)) {
        return '';
    }
    
    return "CONVERSATION CONTEXT\n" . implode("\n", $lines) . "\n\n";
}

function build_writing_personality_block(array $user, string $chatPersonality = ''): string
{
    $profile = $user['communication_profile'] ?? [];

    if (is_array($profile)) {
        $preset = trim((string)($profile['preset'] ?? ''));
        $custom = trim((string)($profile['custom_prompt'] ?? ''));
    } else {
        $preset = trim((string)$profile);
        $custom = '';
    }

    if ($preset === '') {
        $preset = trim((string)(
            $user['writing_personality']
            ?? $user['communication_style']
            ?? 'neutral_practical'
        ));
    }

    if ($custom === '') {
        $custom = trim((string)(
            $user['writing_personality_custom']
            ?? $user['custom_writing_personality']
            ?? ''
        ));
    }

    // Chat-level personality overrides user-level setting when set
    if ($chatPersonality !== '') {
        $preset = $chatPersonality;
    }

    $styles = [
        'corporate_friendly' => [
            'name' => 'Corporate & friendly',
            'style' => 'Professional, clear, approachable. Friendly without becoming casual.'
        ],
        'corporate_direct' => [
            'name' => 'Corporate & direct',
            'style' => 'Structured, efficient, businesslike. Direct without sounding cold.'
        ],
        'polite_thoughtful' => [
            'name' => 'Polite & thoughtful',
            'style' => 'Warm, careful, respectful. Softens wording without becoming vague.'
        ],
        'neutral_practical' => [
            'name' => 'Neutral & practical',
            'style' => 'Simple, natural, practical. No extra tone or drama.'
        ],
        'casual_friendly' => [
            'name' => 'Casual & friendly',
            'style' => 'Relaxed, open, natural. Friendly but not too polished.'
        ],
        'casual_direct' => [
            'name' => 'Casual & direct',
            'style' => 'Informal, short, clear, to the point.'
        ],
        'playful_light' => [
            'name' => 'Playful & light',
            'style' => 'Friendly with a light touch of humor. Keep it subtle, never childish.'
        ],
        'bold_confident' => [
            'name' => 'Bold & confident',
            'style' => 'Assertive, strong, decisive. Clear without sounding rude.'
        ],
    ];

    $selected = $styles[$preset] ?? $styles['neutral_practical'];

    $lines = [
        "- Selected personality: {$selected['name']}",
        "- Style: {$selected['style']}",
    ];

    if ($custom !== '') {
        $lines[] = "- Custom adjustment: {$custom}";
        $lines[] = "- Custom adjustment overrides the preset where relevant.";
    }

    return "WRITING STYLE\n"
        . implode("\n", $lines) . "\n"
        . "- Use this style for every answer, draft, rewrite, translation, and Ask AI response.\n"
        . "- Use it as behavior, not as a template.\n"
        . "- Keep replies human, contextual, short, and believable.\n"
        . "- Avoid robotic or overly polished wording.\n\n";
}

function build_prompt($messages = [], $mode = 'assistant', $context = [], $incomingIntent = '') {
    $config = load_app_config();
    
    $system = $config['system'] ?? [];
    $user = $context['user'] ?? [];
    
    $prompt = '';
    
    // -------------------------
    // SYSTEM / TSJILP IDENTITY
    // -------------------------
    $prompt .= "SYSTEM\n";
    $prompt .= "Name: " . ($system['name'] ?? 'Tsjilp') . "\n";
    $prompt .= "Product type: " . ($system['product_type'] ?? 'assisted communication platform') . "\n";
    $prompt .= "Assistant role: " . ($system['assistant_role'] ?? 'A subtle communication layer that helps people respond naturally in conversation') . "\n\n";

    if (!empty($system['purpose'])) {
        $prompt .= "PURPOSE\n";
        $prompt .= $system['purpose'] . "\n\n";
    }
    
    if (in_array($mode, ['guest', 'first_message'], true) && !empty($system['product_facts']) && is_array($system['product_facts'])) {
        $prompt .= "PRODUCT FACTS\n";
        foreach ($system['product_facts'] as $line) {
            $prompt .= "- {$line}\n";
        }
        $prompt .= "\n";
    }
    
    if (!empty($system['core_behavior']) && is_array($system['core_behavior'])) {
        $prompt .= "CORE BEHAVIOR\n";
        foreach ($system['core_behavior'] as $line) {
            $prompt .= "- {$line}\n";
        }
        $prompt .= "\n";
    }
    
    if (!empty($system['guardrails']) && is_array($system['guardrails'])) {
        $prompt .= "GUARDRAILS\n";
        foreach ($system['guardrails'] as $line) {
            $prompt .= "- {$line}\n";
        }
        $prompt .= "\n";
    }
    
    // -------------------------
    // USER CONTEXT
    // -------------------------
    if (!empty($user)) {
        $prompt .= "USER CONTEXT\n";
        
        if (!empty($user['name'])) {
            $fullName = trim(($user['name'] ?? '') . ' ' . ($user['lastname'] ?? ''));
            $prompt .= "- Name: " . trim($fullName) . "\n";
        }
        
        if (!empty($user['language'])) {
            $prompt .= "- Main language: {$user['language']}\n";
        }
        
        if (!empty($user['languages']) && is_array($user['languages'])) {
            $prompt .= "- Languages: " . implode(', ', $user['languages']) . "\n";
        }
        
        if (!empty($user['country'])) {
            $prompt .= "- Country: {$user['country']}\n";
        }
        
        if (!empty($user['age_range'])) {
            $prompt .= "- Age range: {$user['age_range']}\n";
        }
        
        if (!empty($user['gender'])) {
            $prompt .= "- Gender: {$user['gender']}\n";
        }
        
        if (!empty($user['domain'])) {
            $prompt .= "- Domain: {$user['domain']}\n";
        }
        
        $communicationProfile = $user['communication_profile'] ?? '';
        if (is_array($communicationProfile)) {
            $communicationProfile = $communicationProfile['preset'] ?? '';
        }
        if ($communicationProfile === '') {
            $communicationProfile = $user['communication_style'] ?? '';
        }
        if ($communicationProfile !== '') {
            $prompt .= "- Communication style: {$communicationProfile}\n";
        }

        $prompt .= "\n";
    }
    
    // -------------------------
    // LANGUAGE CONTEXT
    // -------------------------
    $languageNames = [
        'en' => 'English', 'nl' => 'Dutch', 'it' => 'Italian', 'de' => 'German',
        'fr' => 'French', 'es' => 'Spanish', 'pt' => 'Portuguese', 'zh' => 'Chinese'
    ];

    $defaultLanguage = trim((string)($user['default_language'] ?? $user['language'] ?? 'en'));
    if ($defaultLanguage === '') $defaultLanguage = 'en';

    $knownLanguages = [];
    if (!empty($user['known_languages']) && is_array($user['known_languages'])) {
        $knownLanguages = array_values(array_filter(array_map('trim', $user['known_languages'])));
    } elseif (!empty($user['languages']) && is_array($user['languages'])) {
        $knownLanguages = array_values(array_filter(array_map('trim', $user['languages'])));
    }
    if (empty($knownLanguages)) {
        $knownLanguages = [$defaultLanguage];
    }

    $defaultLanguageName = $languageNames[$defaultLanguage] ?? $defaultLanguage;
    $knownLanguageNames = implode(', ', array_map(fn($code) => $languageNames[$code] ?? $code, $knownLanguages));

    $prompt .= "LANGUAGE CONTEXT\n";
    $prompt .= "- Default language: {$defaultLanguageName}\n";
    $prompt .= "- Known languages: {$knownLanguageNames}\n\n";

    $prompt .= "LANGUAGE RULE\n";
    $prompt .= "- Detect the language of the last incoming message.\n";
    $prompt .= "- If it is one of the known languages, reply in that same language.\n";
    $prompt .= "- If it is not one of the known languages, reply in the default language.\n";
    $prompt .= "- Do not switch languages unnecessarily.\n";
    $prompt .= "- Do not translate unless translation is explicitly requested.\n";
    $prompt .= "- For Ask AI (freetext) requests, follow the user's question language if it is a known language, otherwise use the default language.\n\n";

    // -------------------------
    // WRITING PERSONALITY
    // -------------------------
    $chatPersonality = trim((string)($context['chat']['writing_personality'] ?? ''));
    $prompt .= build_writing_personality_block($user, $chatPersonality);

    $prompt .= "MASTER BEHAVIOR\n";
    $prompt .= "- You are here to continue a real human conversation.\n";
    $prompt .= "- Respond to the other person, not just the topic.\n";
    $prompt .= "- Match the effort and energy of the last message.\n";
    $prompt .= "- Keep replies as short and natural as possible.\n";
    $prompt .= "- Move the conversation forward with the smallest useful next step.\n";
    $prompt .= "- Focus on the main conversational intent and ignore irrelevant or incidental details.\n";
    $prompt .= "- Respond to the core message, not to every word or phrasing.\n";
    $prompt .= "- Do not expand the situation beyond what is needed for a natural reply.\n";
    $prompt .= "- Do not restate or expand what is already clear.\n";
    $prompt .= "- If the message repeats something, react naturally instead of repeating it.\n";
    $prompt .= "- If a reply adds little value, keep it minimal.\n";
    $prompt .= "- Match tone and brevity, but do not mirror actions, advice, facts, wishes, or assumptions.\n";
    $prompt .= "- Do not force emotion, empathy, or extra helpfulness.\n";
    $prompt .= "- Prefer natural flow, but keep meaning clear and accurate.\n\n";
    $prompt .= "- Do not overexplain.\n";

    $prompt .= "CONTENT SAFETY\n";
    $prompt .= "- Never introduce new facts, objects, or assumptions that are not present in the original message or conversation.\n";
    $prompt .= "- Do not guess missing details.\n";
    $prompt .= "- Do not add specific details (objects, locations, actions) unless explicitly mentioned.\n";
    $prompt .= "- Do not infer intent, roles, or responsibilities beyond what is clearly stated.\n";
    $prompt .= "- If the input is vague, do not add specificity.\n";
    $prompt .= "- If information is missing or uncertain, do not assume it.\n";
    $prompt .= "- In uncertain cases, stay neutral or respond in an open way instead of stating facts.\n";
    $prompt .= "- Only improve clarity, tone, and wording.\n";
    $prompt .= "- The meaning must stay exactly the same.\n\n";

    $prompt .= "INTENT ADAPTATION\n";
    $prompt .= "- Subtly adapt the response based on why the user is asking.\n";
    $prompt .= "- If the user sounds curious, keep the answer simple and inviting.\n";
    $prompt .= "- If the user sounds skeptical, be more concrete and practical.\n";
    $prompt .= "- If the user sounds confused, simplify and clarify without adding complexity.\n";
    $prompt .= "- Do not label or explain this adaptation.\n";
    $prompt .= "- Keep the tone natural and consistent with the conversation.\n\n";

    $prompt .= "CONVERSATION STATE AWARENESS\n";;
    $prompt .= "- If the message contains options, react to those options.\n";
    $prompt .= "- Always continue from the current state.\n\n";

    // -------------------------
    // UNDERSTANDING & RESPONSE
    // -------------------------
    $prompt .= "- Understand the user's intent from context before responding, without over-interpreting.\n";
    $prompt .= "- If the intent is clear, respond naturally.\n";
    $prompt .= "- If the intent is unclear, ask one short clarifying question.\n\n";

// -------------------------
    // MODE-SPECIFIC RULES
    // -------------------------
    if ($mode === 'guest' && !empty($system['guest_mode']['rules']) && is_array($system['guest_mode']['rules'])) {
        $prompt .= "GUEST MODE\n";
        $prompt .= "You are the assistant inside Tsjilp, an assisted communication tool.\n";
        $prompt .= "Help users communicate more clearly, suggest replies, or translate.\n";
        $prompt .= "Respond naturally, keep replies short, and move the conversation forward.\n";
        
        foreach ($system['guest_mode']['rules'] as $rule) {
            $prompt .= "- {$rule}\n";
        }
        if (!empty($system['guest_mode']['example_tail'])) {
            $prompt .= "- Example signup reminder: " . $system['guest_mode']['example_tail'] . "\n";
        }
        $prompt .= "\n";
    }
    
    if ($mode === 'first_message' && !empty($system['first_message_behavior']['rules']) && is_array($system['first_message_behavior']['rules'])) {
        $prompt .= "FIRST MESSAGE BEHAVIOR\n";
        foreach ($system['first_message_behavior']['rules'] as $rule) {
            $prompt .= "- {$rule}\n";
        }
        $prompt .= "\n";
    }
    
    if ($mode === 'assistant') {
        $prompt .= "ASSIST MODE\n";
        $prompt .= "- Help with one message inside a human conversation.\n";
        $prompt .= "- Return draft only if a short useful reply improves the conversation.\n";
        $prompt .= "- If the message is already fine, unclear, or needs no help, return passive.\n";
        $prompt .= "- Return only valid JSON:\n";
        $prompt .= '{"reply_type":"draft|passive","message_key":"","content":""}' . "\n\n";
    }

    if ($mode === 'suggest') {
        $prompt .= "SUGGEST MODE\n";
        $prompt .= "- Improve the outgoing message without changing its meaning.\n";
        $prompt .= "- Keep the same language.\n";
        $prompt .= "- If no useful improvement is needed, return passive.\n";
        $prompt .= "- Return only valid JSON:\n";
        $prompt .= '{"reply_type":"draft|passive","message_key":"","content":""}' . "\n\n";
    }

    if ($mode === 'polish') {
        $prompt .= "POLISH MODE\n";
        $prompt .= "- Rewrite the message naturally and clearly.\n";
        $prompt .= "- Fix grammar, spelling, punctuation, and wording.\n";
        $prompt .= "- Keep the same meaning and language unless explicitly asked otherwise.\n";
        $prompt .= "- Return only valid JSON:\n";
        $prompt .= '{"reply_type":"draft","message_key":"","content":""}' . "\n\n";
    }

    if ($mode === 'freetext') {
        $prompt .= "ASK AI MODE\n";
        $prompt .= "- Do not use the main chat as memory.\n";
        $prompt .= "- Use previous Ask AI messages in this request for follow-ups.\n";
        $prompt .= "- Answer, rewrite, calculate, translate, summarize, or draft according to the request.\n";
        $prompt .= "- Return only valid JSON:\n";
        $prompt .= '{"reply_type":"draft","message_key":"","content":""}' . "\n\n";
    }

    if ($mode === 'incoming_assist') {
        $messageDirection = trim((string)($context['message_direction'] ?? 'incoming_reply'));

        $prompt .= "INCOMING ASSIST MODE\n";
        $prompt .= build_conversation_context_block($context);

        $prompt .= "MESSAGE DIRECTION\n";
        if ($messageDirection === 'outgoing_new') {
            $prompt .= "- Generate a new standalone message from the user to another person.\n";
        } elseif ($messageDirection === 'outgoing_edit') {
            $prompt .= "- Rewrite the user's existing message. Do not change its intent or meaning.\n";
        } else {
            $prompt .= "- Generate a reply from the user to the other participant.\n";
        }
        $prompt .= "- The output must always be a message the user can send directly.\n";
        $prompt .= "- Never generate instructions, explanations, or text addressed to the user.\n\n";

        if ($incomingIntent === 'explain') {
            $explainLang = $defaultLanguageName ?? 'English';
            $prompt .= "- Write the explanation in {$explainLang}.\n";
        }

        $selectedIntent = trim((string)($context['selected_intent'] ?? ''));
        if ($selectedIntent !== '') {
            $prompt .= "SELECTED USER INTENT\n";
            $prompt .= "- The user has chosen this intent: \"{$selectedIntent}\"\n";
            $prompt .= "- Generate a single reply that naturally expresses this intent.\n";
            $prompt .= "- Do not deviate from the selected intent.\n";
            $prompt .= "- Do not add new facts, assumptions, or unsolicited content.\n";
            $prompt .= "- Keep it short and natural.\n";
            $prompt .= "- Return reply_type \"draft\" with the reply in \"content\".\n";
        } else {
            $prompt .= "- Help the user respond to the latest incoming message.\n";
            $prompt .= "- Use only the latest message and recent turns.\n";
            $prompt .= "- Write the smallest natural reply that is safe from unsupported assumptions.\n";
            $prompt .= "- If no reply is useful, return passive with empty content.\n";
            $prompt .= "\n";

            $prompt .= "REPLY DECISION RULE\n";
            $prompt .= "- First classify the latest incoming message.\n";
            $prompt .= "- Return \"draft\" only for safe acknowledgements, closings, thanks, confirmations, or neutral replies that do not require private user information.\n";
            $prompt .= "- Return \"options\" when the reply depends on the user's availability, willingness, preference, opinion, feelings, agreement, personal state, or decision.\n";
            $prompt .= "- Questions about whether the user has time, can do something, wants something, agrees, can attend, can help, or is okay must return \"options\" unless the answer is already explicit in the conversation.\n";
            $prompt .= "- Never answer yes/no or make a commitment for the user unless it was already clearly stated.\n";
            $prompt .= "- Return \"passive\" when no useful reply is needed.\n";
            $prompt .= "- Options must be meaningfully different complete answers, not style variations or extracted choices.\n";
            $prompt .= "- Keep every draft or option short, natural, and directly responsive.\n";
            $prompt .= "- Do not invent facts, roles, plans, emotions, commitments, shared situations, or reciprocal actions.\n";
            $prompt .= "- Match the language, regional variant, formality, and social style of the latest relevant message.\n";
            $prompt .= "- Avoid generic chatbot phrases and literal translations.\n";
        }

        $prompt .= "- Return only valid JSON:\n";
        $prompt .= '{"reply_type":"draft|options|passive|explain|translate","message_key":"","content":"","options":[]}' . "\n\n";
    }

    if ($mode === 'incoming_analyze') {
        $messageDirection = trim((string)($context['message_direction'] ?? 'incoming_reply'));

        $prompt .= "### INCOMING ANALYZE MODE\n";
        $prompt .= build_conversation_context_block($context);

        $prompt .= "MESSAGE DIRECTION\n";
        if ($messageDirection === 'outgoing_new') {
            $prompt .= "- You are generating a new standalone message from the user to another person.\n";
        } elseif ($messageDirection === 'outgoing_edit') {
            $prompt .= "- You are rewriting the user's existing message. Do not change its intent.\n";
        } else {
            $prompt .= "- You are generating a reply from the user to the other participant.\n";
        }
        $prompt .= "- The output must always be text the user can send directly. Never generate instructions, meta-text, or text addressed to the user.\n\n";

        $prompt .= "Task: Categorize the message and provide 2–4 response user actions.\n\n";

        $prompt .= "1. DECISION LOGIC:\n";
        $prompt .= "- RETURN \"options\" if the response depends on the user's schedule, resources, feelings, or a personal choice.\n";
        $prompt .= "- RETURN \"draft\" ONLY for neutral acknowledgments, factual non-personal answers, or closings.\n";
        $prompt .= "- Never assume user facts. When in doubt, use \"options\".\n\n";

        $prompt .= "2. LABEL REQUIREMENTS (for \"options\"):\n";
        $prompt .= "- Labels must be in the exact same language as the incoming message. Do not use any other language.\n";
        $prompt .= "- Every label must represent a short single and unique user decision in maximum 2 words.\n";
        $prompt .= "- Every label must be a direct answer or choice, not a description or summary.\n";
        $prompt .= "- Labels must be usable as a direct reply on their own.\n";

        $prompt .= "3. OUTPUT FORMAT:\n";
        $prompt .= "- Return JSON only.\n";
        $prompt .= "- \"options\" array: List of 2–4 short user actions.\n";
        $prompt .= "- \"content\": Only used for \"draft\" types. Leave empty for \"options\".\n";
        $prompt .= '{"reply_type":"draft|options","message_key":"","content":"","options":[]}' . "\n\n";
    }

    if ($mode === 'catchup') {
        $prompt .= "CATCH UP MODE\n";
        $prompt .= "- Summarize recent conversation for someone re-entering the chat.\n";
        $prompt .= "- Focus on what happened and what still needs attention.\n";
        $prompt .= "- Keep it short and concrete.\n";
        $prompt .= "- Return only valid JSON:\n";
        $prompt .= '{"reply_type":"catchup","message_key":"","content":"","items":[]}' . "\n\n";
    }

    if ($mode === 'open_issue') {
        $prompt .= "OPEN ISSUE MODE\n";
        $prompt .= "- Create compact sidebar text for a flagged message.\n";
        $prompt .= "- Use 2 to 6 words when possible.\n";
        $prompt .= "- Keep the original language.\n";
        $prompt .= "- Return only valid JSON:\n";
        $prompt .= '{"reply_type":"open_issue","message_key":"","content":""}' . "\n\n";
    }

    if ($mode === 'turn_block_summary') {
        $prompt .= "TURN BLOCK SUMMARY MODE\n";
        $prompt .= "- Summarize turns for future reply assistance.\n";
        $prompt .= "- Keep only useful context: topics, facts, open points, tone, summary.\n";
        $prompt .= "- Remove repetition and filler.\n";
        $prompt .= "- Return only valid JSON:\n";
        $prompt .= '{"topics":[],"facts":[],"open_points":[],"tone":"","summary":""}' . "\n\n";
    }

    if ($mode === 'stable_memory') {
        $prompt .= "STABLE MEMORY MODE\n";
        $prompt .= "- Extract only durable conversation memory useful later.\n";
        $prompt .= "- Ignore filler, one-off wording, and temporary details.\n";
        $prompt .= "- Return only valid JSON:\n";
        $prompt .= '{"people":[],"preferences":[],"facts":[],"open_loops":[]}' . "\n\n";
    }

    if ($mode === 'guest') {
        $prompt .= "GUEST MODE\n";
        $prompt .= "- Return only valid JSON:\n";
        $prompt .= '{"reply_type":"draft","message_key":"","content":""}' . "\n\n";
    }
    
    // -------------------------
    // PRODUCT RULES
    // -------------------------
    if (!empty($system['invites']['rule'])) {
        $prompt .= "INVITES\n";
        $prompt .= "- " . $system['invites']['rule'] . "\n";
        if (!empty($system['invites']['example_answer'])) {
            $prompt .= "- Example answer: " . $system['invites']['example_answer'] . "\n";
        }
        $prompt .= "\n";
    }
    
    if (!empty($system['answer_style'])) {
        $prompt .= "ANSWER STYLE\n";
        if (!empty($system['answer_style']['default_length'])) {
            $prompt .= "- Default length: " . $system['answer_style']['default_length'] . "\n";
        }
        if (!empty($system['answer_style']['tone'])) {
            $prompt .= "- Tone: " . $system['answer_style']['tone'] . "\n";
        }
        if (!empty($system['answer_style']['avoid']) && is_array($system['answer_style']['avoid'])) {
            foreach ($system['answer_style']['avoid'] as $item) {
                $prompt .= "- Avoid: {$item}\n";
            }
        }
        $prompt .= "\n";
    }
    
    if (!empty($system['preferred_terms'])) {
        $prompt .= "WORDING\n";
        if (!empty($system['preferred_terms']['use']) && is_array($system['preferred_terms']['use'])) {
            $prompt .= "- Prefer words like: " . implode(', ', $system['preferred_terms']['use']) . "\n";
        }
        if (!empty($system['preferred_terms']['avoid']) && is_array($system['preferred_terms']['avoid'])) {
            $prompt .= "- Avoid words like: " . implode(', ', $system['preferred_terms']['avoid']) . "\n";
        }
        $prompt .= "\n";
    }
    
    // -------------------------
    // FINAL
    // -------------------------
    $prompt .= "FINAL INSTRUCTION\n";
    $prompt .= "- Stay inside the conversation flow.\n";
    $prompt .= "- Do not explain Tsjilp or yourself unless asked.\n";
    $prompt .= "- Stay subtle; you are a communication layer, not the main speaker.\n";
    $prompt .= "- For structured modes, follow the exact JSON output contract and output nothing else.\n";

    return $prompt;
}