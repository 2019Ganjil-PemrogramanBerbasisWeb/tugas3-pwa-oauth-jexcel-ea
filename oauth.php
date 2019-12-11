<?php
// Dapet Dari Github App Client Id, Client Secret, dan API Name dan harus diset
define('OAUTH2_CLIENT_ID', '');
define('OAUTH2_CLIENT_SECRET', '');
define('API_NAME', '');

// Link Otorisasi, Access Token, dan URL Base
$authorizeURL = 'https://github.com/login/oauth/authorize';
$tokenURL = 'https://github.com/login/oauth/access_token';
$apiURLBase = 'https://api.github.com/';

session_start();

// Check kalau ingin logout, access_token dihapus
if(get('action') == 'logout') {
    unset($_SESSION['access_token']);
    session_destroy();
}

// Mulai proses login
if(get('action') == 'login') {
  // Buat random hash 'state' untuk pengecekan dan keamanan
  $_SESSION['state'] = hash('sha256', microtime(TRUE).rand().$_SERVER['REMOTE_ADDR']);
  unset($_SESSION['access_token']);
  // Buat parameter untuk dikirim ke laman otorisasi github
  $params = array(
    'client_id' => OAUTH2_CLIENT_ID,
    'redirect_uri' => 'http://' . $_SERVER['SERVER_NAME'] . $_SERVER['PHP_SELF'],
    'scope' => 'user',
    'state' => $_SESSION['state']
  );
  // Redirect ke laman ototrisasi github dengan parameternya
  header('Location: ' . $authorizeURL . '?' . http_build_query($params));
  die();
}

// Setelah otorosisasi lewat github, akan diredirect kembali dengan query 'code' dan 'state'
if(get('code')) {
  // Verifikasi 'state' yang dikirimkan github sama dengan 'state' di session
  if(!get('state') || $_SESSION['state'] != get('state')) {
    header('Location: ' . $_SERVER['PHP_SELF']);
    die();
  }
  // Request akses token ke github dengan 'client_id', 'client_secret', dan 'code' yang sudah diberikan github
  $token = apiRequest($tokenURL, array(
    'client_id' => OAUTH2_CLIENT_ID,
    'client_secret' => OAUTH2_CLIENT_SECRET,
    'redirect_uri' => 'http://' . $_SERVER['SERVER_NAME'] . $_SERVER['PHP_SELF'],
    'state' => $_SESSION['state'],
    'code' => get('code')
  ));
  // Simpan 'access_token' yang diberikan dari github ke session
  $_SESSION['access_token'] = $token->access_token;
  header('Location: ' . $_SERVER['PHP_SELF']);
}

// Setelah 'access_token' didapat akan merequest informasi user ke github menggunakan 'access_token' yang didapat
if (session('access_token')) {
  // Request informasi user
  $user = apiRequest($apiURLBase.'user');
  ?>
    <h3>Logged In</h3>
    <h4>Username: <?php echo $user->name; ?></h4>
    <pre>Access Token: <?php echo $_SESSION['access_token']; ?></pre>
    <pre><?php print_r($user) ?></pre>
    <p><a href="?action=logout">Log Out</a></p>
  <?php
} else {
  // Kalau 'access_token' tidak ada, berarti user harus login terlebih dahulu
  ?>
    <h3>Not Logged In</h3>
    <p><a href="?action=login">Log In</a></p>
  <?php
}
 
/*==============Fungsi=============== */

// Fungsi requestAPI menggunakan curl
function apiRequest($url, $post=FALSE, $headers=array()) {
  // Inisialisasi curl ke url yang ingin dituju
  $ch = curl_init($url);
  // Set supaya hasil eksekusi curl di return
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
  // Kalau $post ada, maka set pada PostFields berdasarkan query post yang didapat
  if($post)
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post));
  
  // Set Header agar data yang didapat dalam bentuk json
  $headers[] = 'Accept: application/json';
  // Set Header agar url yang dituju mengetahui user-agent (nama API) yang digunakan
  $headers[] = 'User-Agent: '.API_NAME.'-Api';
  // Kalau sudah ada 'access_token', set header otorisasinya dengan access tokennya
  if(session('access_token'))
    $headers[] = 'Authorization: Bearer ' . session('access_token');

  // Set Header ke dalam url yang akan dieksekusi
  curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
  // Eksekusi curl ke url yang dengan header yang diset
  $response = curl_exec($ch);
  // Return fungsi dengan mendecode hasil respose curl (json) menjadi bentuk objectnya
  return json_decode($response);
}

// Fungsi get untuk memudahkan mengecek variable $_GET[]
function get($key, $default=NULL) {
  // Ternary untuk ngecek key yang ada dalam $_GET
  return array_key_exists($key, $_GET) ? $_GET[$key] : $default;
}

// Fungsi session untuk memudahkan mengecek variable $_SESSION[]
function session($key, $default=NULL) {
  // Ternary untuk mengecek key yang ada dalam $_SESSION
  return array_key_exists($key, $_SESSION) ? $_SESSION[$key] : $default;
}
