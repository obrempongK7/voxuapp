<?php
/**
 * Voxu — Internationalization (i18n) System
 * Supports 15 languages. Loaded once per request via session.
 * @author  Jcode | ObrempongK
 */

// All supported languages
define('VOXU_LANGUAGES', [
    'en'    => ['name' => 'English',                'native' => 'English',      'flag' => '🇺🇸', 'rtl' => false],
    'zh'    => ['name' => 'Chinese (Simplified)',    'native' => '中文',          'flag' => '🇨🇳', 'rtl' => false],
    'es'    => ['name' => 'Spanish',                 'native' => 'Español',      'flag' => '🇪🇸', 'rtl' => false],
    'ar'    => ['name' => 'Arabic',                  'native' => 'العربية',      'flag' => '🇸🇦', 'rtl' => true],
    'pt'    => ['name' => 'Portuguese',              'native' => 'Português',    'flag' => '🇧🇷', 'rtl' => false],
    'fr'    => ['name' => 'French',                  'native' => 'Français',     'flag' => '🇫🇷', 'rtl' => false],
    'sw'    => ['name' => 'Swahili',                 'native' => 'Kiswahili',    'flag' => '🇰🇪', 'rtl' => false],
    'ha'    => ['name' => 'Hausa',                   'native' => 'Hausa',        'flag' => '🇳🇬', 'rtl' => false],
    'de'    => ['name' => 'German',                  'native' => 'Deutsch',      'flag' => '🇩🇪', 'rtl' => false],
    'no'    => ['name' => 'Norwegian',               'native' => 'Norsk',        'flag' => '🇳🇴', 'rtl' => false],
    'ja'    => ['name' => 'Japanese',                'native' => '日本語',        'flag' => '🇯🇵', 'rtl' => false],
    'hi'    => ['name' => 'Hindi',                   'native' => 'हिन्दी',       'flag' => '🇮🇳', 'rtl' => false],
    'ko'    => ['name' => 'Korean',                  'native' => '한국어',        'flag' => '🇰🇷', 'rtl' => false],
    'ru'    => ['name' => 'Russian',                 'native' => 'Русский',      'flag' => '🇷🇺', 'rtl' => false],
    'id'    => ['name' => 'Indonesian/Malay',        'native' => 'Bahasa',       'flag' => '🇮🇩', 'rtl' => false],
]);

// Translation strings — add more as needed
// Format: 'key' => ['en' => '...', 'fr' => '...', ...]
$VOXU_STRINGS = [
    'home'            => ['en'=>'Home',          'fr'=>'Accueil',     'es'=>'Inicio',      'ar'=>'الرئيسية',   'pt'=>'Início',      'zh'=>'主页',    'sw'=>'Nyumbani',  'ha'=>'Gida',       'de'=>'Startseite', 'no'=>'Hjem',     'ja'=>'ホーム',    'hi'=>'होम',         'ko'=>'홈',      'ru'=>'Главная',  'id'=>'Beranda'],
    'explore'         => ['en'=>'Explore',       'fr'=>'Explorer',    'es'=>'Explorar',    'ar'=>'استكشف',     'pt'=>'Explorar',    'zh'=>'探索',    'sw'=>'Gundua',    'ha'=>'Bincika',    'de'=>'Entdecken',  'no'=>'Utforsk',  'ja'=>'探索',     'hi'=>'एक्सप्लोर', 'ko'=>'탐색',    'ru'=>'Обзор',    'id'=>'Jelajahi'],
    'notifications'   => ['en'=>'Notifications', 'fr'=>'Notifications','es'=>'Notificaciones','ar'=>'الإشعارات', 'pt'=>'Notificações','zh'=>'通知',    'sw'=>'Arifa',     'ha'=>'Sanarwa',    'de'=>'Benachrichtigungen','no'=>'Varsler','ja'=>'通知',   'hi'=>'सूचनाएं',   'ko'=>'알림',    'ru'=>'Уведомления','id'=>'Notifikasi'],
    'messages'        => ['en'=>'Messages',      'fr'=>'Messages',    'es'=>'Mensajes',    'ar'=>'الرسائل',    'pt'=>'Mensagens',   'zh'=>'消息',    'sw'=>'Ujumbe',    'ha'=>'Saƙo',       'de'=>'Nachrichten', 'no'=>'Meldinger','ja'=>'メッセージ','hi'=>'संदेश',   'ko'=>'메시지',  'ru'=>'Сообщения','id'=>'Pesan'],
    'wallet'          => ['en'=>'Wallet',        'fr'=>'Portefeuille','es'=>'Billetera',   'ar'=>'المحفظة',    'pt'=>'Carteira',    'zh'=>'钱包',    'sw'=>'Mkoba',     'ha'=>'Jakar kuɗi','de'=>'Geldbörse',  'no'=>'Lommebok', 'ja'=>'ウォレット','hi'=>'वॉलेट',  'ko'=>'지갑',    'ru'=>'Кошелёк',  'id'=>'Dompet'],
    'profile'         => ['en'=>'Profile',       'fr'=>'Profil',      'es'=>'Perfil',      'ar'=>'الملف الشخصي','pt'=>'Perfil',   'zh'=>'个人资料', 'sw'=>'Wasifu',    'ha'=>'Bayani',     'de'=>'Profil',     'no'=>'Profil',   'ja'=>'プロフィール','hi'=>'प्रोफ़ाइल','ko'=>'프로필', 'ru'=>'Профиль',  'id'=>'Profil'],
    'for_you'         => ['en'=>'For You',       'fr'=>'Pour vous',   'es'=>'Para ti',     'ar'=>'لك',         'pt'=>'Para você',   'zh'=>'为你',    'sw'=>'Kwa Wewe',  'ha'=>'Gare Ku',    'de'=>'Für dich',   'no'=>'For deg',  'ja'=>'あなたへ',  'hi'=>'आपके लिए', 'ko'=>'추천',    'ru'=>'Для вас',  'id'=>'Untukmu'],
    'following'       => ['en'=>'Following',     'fr'=>'Abonnements', 'es'=>'Siguiendo',   'ar'=>'متابعون',    'pt'=>'Seguindo',    'zh'=>'关注',    'sw'=>'Wanaofuatwa','ha'=>'Biyo biye',  'de'=>'Folge ich',  'no'=>'Følger',   'ja'=>'フォロー中','hi'=>'फ़ॉलोइंग', 'ko'=>'팔로잉',  'ru'=>'Подписки', 'id'=>'Mengikuti'],
    'post_voice'      => ['en'=>'Voice Post',    'fr'=>'Post vocal',  'es'=>'Post de voz', 'ar'=>'منشور صوتي', 'pt'=>'Post de voz', 'zh'=>'语音帖',  'sw'=>'Chapisho cha Sauti','ha'=>'Sauti Post','de'=>'Sprachbeitrag','no'=>'Stemmepost','ja'=>'音声投稿','hi'=>'वॉइस पोस्ट','ko'=>'음성 게시물','ru'=>'Голосовой пост','id'=>'Posting Suara'],
    'deposit'         => ['en'=>'Deposit',       'fr'=>'Dépôt',       'es'=>'Depósito',    'ar'=>'إيداع',      'pt'=>'Depósito',    'zh'=>'存款',    'sw'=>'Amana',     'ha'=>'Adana',      'de'=>'Einzahlung', 'no'=>'Innskudd', 'ja'=>'入金',     'hi'=>'जमा',       'ko'=>'입금',    'ru'=>'Пополнить','id'=>'Setoran'],
    'withdraw'        => ['en'=>'Withdraw',      'fr'=>'Retirer',     'es'=>'Retirar',     'ar'=>'سحب',        'pt'=>'Sacar',       'zh'=>'提款',    'sw'=>'Toa pesa',  'ha'=>'Cirewa',     'de'=>'Auszahlen',  'no'=>'Ta ut',    'ja'=>'引き出し',  'hi'=>'निकासी',   'ko'=>'출금',    'ru'=>'Вывести',  'id'=>'Tarik'],
    'trending'        => ['en'=>'Trending',      'fr'=>'Tendances',   'es'=>'Tendencias',  'ar'=>'الأكثر رواجاً','pt'=>'Tendências','zh'=>'热门',   'sw'=>'Maarufu',   'ha'=>'Shahararru', 'de'=>'Trends',     'no'=>'Trender',  'ja'=>'トレンド',  'hi'=>'ट्रेंडिंग', 'ko'=>'트렌딩',  'ru'=>'В тренде', 'id'=>'Tren'],
    'who_to_follow'   => ['en'=>'Who to follow', 'fr'=>'Qui suivre',  'es'=>'A quién seguir','ar'=>'من تتابع',  'pt'=>'Quem seguir','zh'=>'推荐关注', 'sw'=>'Nani wa kufuata','ha'=>'Wanda za bi bi biye','de'=>'Wem folgen','no'=>'Hvem å følge','ja'=>'おすすめユーザー','hi'=>'किसे फ़ॉलो करें','ko'=>'팔로우 추천','ru'=>'Кого читать','id'=>'Siapa yang diikuti'],
    'share_voice'     => ['en'=>'Share your voice…','fr'=>'Partagez votre voix…','es'=>'Comparte tu voz…','ar'=>'شارك صوتك…','pt'=>'Compartilhe sua voz…','zh'=>'分享你的声音…','sw'=>'Shiriki sauti yako…','ha'=>'Raba muryarka…','de'=>'Teile deine Stimme…','no'=>'Del stemmen din…','ja'=>'声を届けよう…','hi'=>'अपनी आवाज़ शेयर करें…','ko'=>'목소리를 공유하세요…','ru'=>'Поделитесь голосом…','id'=>'Bagikan suaramu…'],
    'podcasts'        => ['en'=>'Podcasts',      'fr'=>'Podcasts',    'es'=>'Podcasts',    'ar'=>'البودكاست',  'pt'=>'Podcasts',    'zh'=>'播客',    'sw'=>'Podikasti',  'ha'=>'Podkast',    'de'=>'Podcasts',   'no'=>'Podkaster','ja'=>'ポッドキャスト','hi'=>'पॉडकास्ट', 'ko'=>'팟캐스트','ru'=>'Подкасты', 'id'=>'Podcast'],
    'status'          => ['en'=>'Status',        'fr'=>'Statut',      'es'=>'Estado',      'ar'=>'الحالة',     'pt'=>'Status',      'zh'=>'状态',    'sw'=>'Hali',       'ha'=>'Yanayin',    'de'=>'Status',     'no'=>'Status',   'ja'=>'ステータス','hi'=>'स्टेटस',   'ko'=>'스토리',  'ru'=>'Статус',   'id'=>'Status'],
    'premium'         => ['en'=>'Premium',       'fr'=>'Premium',     'es'=>'Premium',     'ar'=>'المميز',     'pt'=>'Premium',     'zh'=>'高级',    'sw'=>'Malipo',     'ha'=>'Premium',    'de'=>'Premium',    'no'=>'Premium',  'ja'=>'プレミアム','hi'=>'प्रीमियम', 'ko'=>'프리미엄','ru'=>'Премиум',  'id'=>'Premium'],
    'record_voice'    => ['en'=>'Record Voice',  'fr'=>'Enregistrer', 'es'=>'Grabar voz',  'ar'=>'تسجيل صوت',  'pt'=>'Gravar voz',  'zh'=>'录制声音', 'sw'=>'Rekodi sauti','ha'=>'Rikodi muryar','de'=>'Aufnehmen',  'no'=>'Ta opp',   'ja'=>'録音する',  'hi'=>'आवाज़ रिकॉर्ड करें','ko'=>'녹음하기','ru'=>'Записать','id'=>'Rekam Suara'],
];

/**
 * Get the current active language code (from session > cookie > browser > default 'en').
 */
function getCurrentLang(): string {
    // 1. Session preference
    if (!empty($_SESSION['voxu_lang'])) {
        $l = $_SESSION['voxu_lang'];
        if (array_key_exists($l, VOXU_LANGUAGES)) return $l;
    }
    // 2. Cookie
    if (!empty($_COOKIE['voxu_lang'])) {
        $l = $_COOKIE['voxu_lang'];
        if (array_key_exists($l, VOXU_LANGUAGES)) return $l;
    }
    // 3. Browser Accept-Language
    $accept = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? 'en';
    $parts  = explode(',', $accept);
    foreach ($parts as $part) {
        $code = strtolower(trim(explode(';', $part)[0]));
        $short = explode('-', $code)[0];
        if (array_key_exists($code, VOXU_LANGUAGES))  return $code;
        if (array_key_exists($short, VOXU_LANGUAGES)) return $short;
    }
    return 'en';
}

/**
 * Translate a string key.
 * Usage: __('home') or __('home', 'fr')
 */
function __(string $key, ?string $lang = null): string {
    global $VOXU_STRINGS;
    $lang = $lang ?? getCurrentLang();
    return $VOXU_STRINGS[$key][$lang]
        ?? $VOXU_STRINGS[$key]['en']
        ?? $key;
}

/**
 * Set language (saves to session + cookie).
 */
function setLanguage(string $lang): void {
    if (!array_key_exists($lang, VOXU_LANGUAGES)) $lang = 'en';
    $_SESSION['voxu_lang'] = $lang;
    setcookie('voxu_lang', $lang, time() + 60*60*24*365, '/', '', false, false);
}

/**
 * Is current language RTL?
 */
function isRtl(): bool {
    return VOXU_LANGUAGES[getCurrentLang()]['rtl'] ?? false;
}

function getI18nStrings(string $lang = 'en'): array {
    global $VOXU_STRINGS;
    $out = [];
    foreach ($VOXU_STRINGS as $key => $translations) {
        $out[$key] = $translations[$lang] ?? $translations['en'] ?? $key;
    }
    return $out;
}
