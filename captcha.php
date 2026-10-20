<?php
// captcha.php - Continuously Animated Sliced & Rubbing SVG CAPTCHA Generator
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Generate simple random math problem
$num1 = rand(1, 9);
$num2 = rand(1, 9);
$_SESSION['captcha_ans'] = $num1 + $num2;

$captcha_text = "$num1 + $num2 = ?";

// Send SVG headers
header('Content-Type: image/svg+xml');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');
?>
<svg width="160" height="44" xmlns="http://www.w3.org/2000/svg">
  <defs>
    <!-- Clip Top Half: y=0 to y=22 -->
    <clipPath id="top-half">
      <rect x="0" y="0" width="160" height="22" />
    </clipPath>
    <!-- Clip Bottom Half: y=22 to y=44 -->
    <clipPath id="bottom-half">
      <rect x="0" y="22" width="160" height="22" />
    </clipPath>
  </defs>

  <style>
    @keyframes rubTop {
      0%   { transform: translate(-4px, 0); }
      50%  { transform: translate(4px, 0); }
      100% { transform: translate(-4px, 0); }
    }
    @keyframes rubBottom {
      0%   { transform: translate(4px, 0); }
      50%  { transform: translate(-4px, 0); }
      100% { transform: translate(4px, 0); }
    }
    .top-layer {
      animation: rubTop 1.6s ease-in-out infinite;
    }
    .bottom-layer {
      animation: rubBottom 1.6s ease-in-out infinite;
    }
  </style>

  <!-- Background Box -->
  <rect width="100%" height="100%" fill="#e2e8f0" rx="6" stroke="#94a3b8" stroke-width="1.5"/>

  <!-- Top Half Layer (Animates back & forth to left/right) -->
  <g clip-path="url(#top-half)" class="top-layer">
    <text x="50%" y="50%" dominant-baseline="central" text-anchor="middle" font-family="'Courier New', Courier, monospace" font-size="22" font-weight="bold" fill="#0b3c5d" letter-spacing="4">
      <?php echo $captcha_text; ?>
    </text>
  </g>

  <!-- Bottom Half Layer (Animates back & forth in opposite direction) -->
  <g clip-path="url(#bottom-half)" class="bottom-layer">
    <text x="50%" y="50%" dominant-baseline="central" text-anchor="middle" font-family="'Courier New', Courier, monospace" font-size="22" font-weight="bold" fill="#0b3c5d" letter-spacing="4">
      <?php echo $captcha_text; ?>
    </text>
  </g>
</svg>
