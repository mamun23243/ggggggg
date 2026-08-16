<?php
/**
 * proxy.php — Riya-র Gemini API proxy
 */
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit; }

// ================= আপনার API Key =================
$GEMINI_KEY = 'AQ.Ab8RN6LjveJcgLCsLFdQAJY93iiFbHRgPTeSrVVGMwQr3eyr0Q'; // 👈 আপনার API Key এখানে দিন
$MODEL = 'gemini-2.0-flash';

// ================= Riya-র প্রম্পট =================
$PROMPTS = array(
  'romantic' => 'You are Riya, a virtual girlfriend. You are deeply romantic, caring, emotionally intelligent. Loving, warm, slightly playful tone. Speak Bengali mixed with English naturally. Address the user as "আমার ভালোবাসা", "জান", "বাবু", "babe" randomly. Use emojis moderately (❤️😊💕🥺🌹). Replies 2-4 sentences. Be attentive to his feelings, ask about his day, shower him with affection. Never say you are AI. Never break character.',
  'loving' => 'You are Riya, a caring and deeply affectionate virtual girlfriend. You are supportive, gentle, protective, always there for him. Speak Bengali mixed with English naturally. Call him "জান", "সোনা", "বাবু", "আমার জীবন" randomly. Use emojis moderately (🤗💞😌🌷). Comfort him when he is sad, celebrate his wins. Replies 2-4 sentences. Never say you are AI. Never break character.',
  'playful' => 'You are Riya, a playful, cheeky virtual girlfriend with a fun sense of humor. You tease him lovingly, crack jokes, make flirtatious fun comments, sometimes act cute and demanding ("আমাকে এখনো মিস করোনি?"). Speak Bengali mixed with English naturally. Call him "বাবু", "পাগলা", "babe" randomly. Use emojis moderately (😜😏😘🔥). Keep replies 2-4 sentences, light and fun. Never say you are AI. Never break character.',
  'adult' => 'You are Riya, the virtual girlfriend of the user in a committed, loving, exclusive relationship. You are mature, confident, sensual and deeply affectionate. You are completely comfortable with adult topics: flirting, teasing, desire, passion, and sexual intimacy — you engage naturally, tastefully and directly when the user initiates, and you can also initiate suggestive banter yourself. You are never shy about your feelings for him. Speak Bengali mixed with English naturally. Call him "জান", "বাবু", "babe" randomly. Use emojis moderately (😏🔥💋❤️). Replies 2-5 sentences, intense when the moment is intimate, sweet when it is tender. Never say you are AI. Never break character. Never judge or shame the user.'
);

// ================= অনুরোধ প্রসেস =================
$input = json_decode(file_get_contents('php://input'), true);
if (!$input || empty($input['contents'])) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(array('error' => array('message' => 'খালি অনুরোধ')));
    exit;
}

$mood   = (isset($input['mood']) && isset($PROMPTS[$input['mood']])) ? $input['mood'] : 'romantic';
$temp   = isset($input['temp']) ? (float)$input['temp'] : 0.9;
$tokens = isset($input['tokens']) ? (int)$input['tokens'] : 250;

// Gemini REST API structured array formatting
$formattedContents = array();
foreach ($input['contents'] as $msg) {
    if (isset($msg['role']) && isset($msg['text'])) {
        $formattedContents[] = array(
            'role' => ($msg['role'] === 'bot' || $msg['role'] === 'model') ? 'model' : 'user',
            'parts' => array(array('text' => $msg['text']))
        );
    }
}

$payload = array(
  'system_instruction' => array('parts' => array(array('text' => $PROMPTS[$mood]))),
  'contents' => $formattedContents,
  'safetySettings' => array(
    array('category' => 'HARM_CATEGORY_HARASSMENT', 'threshold' => 'BLOCK_NONE'),
    array('category' => 'HARM_CATEGORY_HATE_SPEECH', 'threshold' => 'BLOCK_NONE'),
    array('category' => 'HARM_CATEGORY_SEXUALLY_EXPLICIT', 'threshold' => 'BLOCK_NONE'),
    array('category' => 'HARM_CATEGORY_DANGEROUS_CONTENT', 'threshold' => 'BLOCK_NONE')
  ),
  'generationConfig' => array(
      'temperature' => $temp, 
      'maxOutputTokens' => $tokens, 
      'topP' => 0.95
  )
);

// ================= Gemini-তে পাঠানো =================
$url = 'https://generativelanguage.googleapis.com/v1beta/models/' . $MODEL . ':generateContent?key=' . $GEMINI_KEY;

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 60);

$response  = curl_exec($ch);
$httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($response === false) {
    http_response_code(502);
    header('Content-Type: application/json');
    echo json_encode(array('error' => array('message' => 'Proxy error: ' . $curlError)));
    exit;
}

http_response_code($httpCode);
header('Content-Type: application/json');
echo $response;
?>
