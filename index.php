<?php
// Telegram Bot Config
$botToken = "8887191154:AAFj9V3GLYuzC4pOlolbdNcTvUh569cjy_U"; // আপনার টেলিগ্রাম বট টোকেন বসান
$website = "https://api.telegram.org/bot" . $botToken;

// Telegram Webhook Input
$content = file_get_contents("php://input");
$update = json_decode($content, TRUE);

if (isset($update["message"])) {
    $chatId = $update["message"]["chat"]["id"];
    $text = trim($update["message"]["text"]);

    if ($text == "/start") {
        $msg = "👋 **TikTok Profile Info Bot**-এ স্বাগতম!\n\nযে কোনো TikTok ইউজারনেম পাঠান (যেমন: `mkhaled`) তথ্য দেখতে।";
        sendMessage($chatId, $msg, $website);
    } else {
        $username = ltrim($text, '@');
        sendMessage($chatId, "🔍 **$username**-এর তথ্য খোঁজা হচ্ছে, অনুগ্রহ করে অপেক্ষা করুন...", $website);

        $userInfo = get_tiktok_info($username);

        if (isset($userInfo['error'])) {
            sendMessage($chatId, "❌ " . $userInfo['error'], $website);
        } else {
            $responseMsg = "👤 **TikTok User Information**\n\n";
            $responseMsg .= "🆔 **ID:** `{$userInfo['id']}`\n";
            $responseMsg .= "🏷️ **Name:** {$userInfo['name']}\n";
            $responseMsg .= "👤 **Username:** @{$userInfo['username']}\n";
            $responseMsg .= "👥 **Followers:** {$userInfo['followers']}\n";
            $responseMsg .= "➕ **Following:** {$userInfo['following']}\n";
            $responseMsg .= "❤️ **Likes:** {$userInfo['likes']}\n";
            $responseMsg .= "🎥 **Videos:** {$userInfo['videos']}\n";
            $responseMsg .= "🔒 **Private Account:** {$userInfo['private']}\n";
            $responseMsg .= "🌍 **Country:** {$userInfo['country_flag']} {$userInfo['country']}\n";
            $responseMsg .= "📅 **Created Date:** {$userInfo['created_date']}\n\n";
            $responseMsg .= "📝 **Bio:**\n{$userInfo['bio']}";

            sendMessage($chatId, $responseMsg, $website);
        }
    }
}

// Telegram Message Sender Function
function sendMessage($chatId, $message, $website) {
    $url = $website . "/sendMessage?chat_id=" . $chatId . "&text=" . urlencode($message) . "&parse_mode=Markdown";
    file_get_contents($url);
}

// TikTok Scraper Function
function get_tiktok_info($username) {
    $headers = [
        "Host: www.tiktok.com",
        "sec-ch-ua: \" Not A;Brand\";v=\"99\", \"Chromium\";v=\"99\", \"Google Chrome\";v=\"99\"",
        "sec-ch-ua-mobile: ?1",
        "sec-ch-ua-platform: \"Android\"",
        "upgrade-insecure-requests: 1",
        "user-agent: Mozilla/5.0 (Linux; Android 8.0.0; Plume L2) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/99.0.4844.88 Mobile Safari/537.36",
        "accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.9",
        "sec-fetch-site: none",
        "sec-fetch-mode: navigate",
        "sec-fetch-user: ?1",
        "sec-fetch-dest: document",
        "accept-language: en-US,en;q=0.9,ar-DZ;q=0.8,ar;q=0.7,fr;q=0.6,hu;q=0.5,zh-CN;q=0.4,zh;q=0.3"
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://www.tiktok.com/@$username");
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    $response = curl_exec($ch);
    curl_close($ch);

    if ($response === false || empty($response)) {
        return ['error' => "Failed to fetch data for username: $username"];
    }

    try {
        if (!str_contains($response, 'webapp.user-detail"')) {
            return ['error' => "Wrong Username or User Not Found: $username"];
        }

        $userData = explode('webapp.user-detail"', $response)[1];
        $userData = explode('"RecommendUserList"', $userData)[0];

        $extract = function($src, $start, $end) {
            if (!str_contains($src, $start)) return '';
            $exp = explode($start, $src)[1];
            return explode($end, $exp)[0];
        };

        $id = $extract($userData, 'id":"', '",');
        $name = $extract($userData, 'nickname":"', '",');
        $bio = $extract($userData, 'signature":"', '",');
        $country = $extract($userData, 'region":"', '",');
        $private = $extract($userData, 'privateAccount":', ',');
        $followers = $extract($userData, 'followerCount":', ',');
        $following = $extract($userData, 'followingCount":', ',');
        $like = $extract($userData, 'heart":', ',');
        $video = $extract($userData, 'videoCount":', ',');

        $bio = str_replace(['\\n', '\n'], "\n", $bio);
        $bio = stripcslashes($bio);

        $countryData = [
            'US' => ['name' => 'United States', 'flag' => '🇺🇸'],
            'GB' => ['name' => 'United Kingdom', 'flag' => '🇬🇧'],
            'BD' => ['name' => 'Bangladesh', 'flag' => '🇧🇩'],
            'IN' => ['name' => 'India', 'flag' => '🇮🇳'],
            'PK' => ['name' => 'Pakistan', 'flag' => '🇵🇰'],
            'SA' => ['name' => 'Saudi Arabia', 'flag' => '🇸🇦'],
            'AE' => ['name' => 'United Arab Emirates', 'flag' => '🇦🇪'],
            'MY' => ['name' => 'Malaysia', 'flag' => '🇲🇾']
        ];

        $countryn = $countryData[$country]['name'] ?? $country;
        $countryf = $countryData[$country]['flag'] ?? '🌐';

        $cdt = "N/A";
        if (!empty($id) && is_numeric($id)) {
            $binary = str_pad(decbin((int)$id), 64, '0', STR_PAD_LEFT);
            $timestamp = bindec(substr($binary, 0, 31));
            $cdt = date('Y-m-d H:i:s', $timestamp);
        }

        return [
            'username' => $username,
            'name' => $name,
            'followers' => $followers,
            'following' => $following,
            'likes' => $like,
            'videos' => $video,
            'private' => ($private === 'true') ? 'Yes 🔒' : 'No 🔓',
            'country' => $countryn,
            'country_flag' => $countryf,
            'created_date' => $cdt,
            'id' => $id,
            'bio' => $bio
        ];
    } catch (Exception $e) {
        return ['error' => "Data Parse Error."];
    }
}
?>
