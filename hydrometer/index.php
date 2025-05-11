<?php 

function tail($fname) {
    $line = '';

    $f = fopen($fname, 'r');
    $cursor = -1;

    fseek($f, $cursor, SEEK_END);
    $char = fgetc($f);

    /**
     * Trim trailing newline chars of the file
     */
    while ($char === "\n" || $char === "\r") {
        fseek($f, $cursor--, SEEK_END);
        $char = fgetc($f);
    }

    /**
     * Read until the start of file or first newline char
     */
    while ($char !== false && $char !== "\n" && $char !== "\r") {
        /**
         * Prepend the new char
         */
        $line = $char . $line;
        fseek($f, $cursor--, SEEK_END);
        $char = fgetc($f);
    }

    fclose($f);

    return $line;
}

// Takes raw data from the request
$json = file_get_contents('php://input');

$tilt_file = "tilt.csv";

if ( $json ) {
    error_log($json);

    // {"temp": 24.50588, "tilt": -80.53409, "boardid": "e6614124b68bc36", "datetime": "2025-05-08 09:38:59", "roll": -0.2620112}
    $o = json_decode($json);

    $myfile = fopen($tilt_file, "a") or die("Unable to open file!");
    fwrite($myfile, '"' . date("Y-m-d H:i:s") . '",' . $o->tilt . ',' . $o->roll . ',' . $o->temp . ',' . $o->bv . "\n");
    fclose($myfile);
    
    $obj = array('OK' => 60 * 60); // keep to short for calibration testing
    echo json_encode($obj);

} else {
    
    $s = time();
    list($d, $t, $r, $T, $bv) = explode(",", tail($tilt_file));
    $t_az = 90-abs($t);
    $sg = 0.754 + 0.122 * $t_az + -0.0185 * pow($t_az,2) + 0.00103 * pow($t_az,3);
    if ( $sg >= 1.000 || $sg < 1.100 ) {
        $sg = number_format((float)$sg, 3, '.', '');
    } else {
        $sg = '';
    }

    $d = trim($d,'"');
    $t = number_format((float)$t, 2, '.', '');
    $t_az = number_format((float)$t_az, 2, '.', '');
    $r = number_format((float)$r, 2, '.', '');
    $T = number_format((float)$T, 2, '.', '');
    $bv = number_format((float)$bv, 2, '.', '');

?>
<!DOCTYPE html>
<head>
<meta charset="utf-8">
<meta http-equiv="cache-control" content="no-cache" />
<meta http-equiv="expires" content="Tue, 01 Jan 1980 1:00:00 GMT" />
<meta http-equiv="pragma" content="no-cache" />
<title>Tilt, Roll, Temperature and Battery Voltage</title>
<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-SgOJa3DmI69IUzQ2PVdRZhwQ+dy64/BUtbMJw1MZ8t5HZApcHrRKUc4W0kG879m7" crossorigin="anonymous">
<script defer src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/js/bootstrap.bundle.min.js"></script>
<style>
img { 
  width: 50%; 
  margin: auto; 
} 

@media screen and (min-width: 480px) {
  img { 
    width: 80%; 
    margin: auto; 
  } 
}
</style>
</head>
<body>
<nav class="navbar bg-body-tertiary">
  <div class="container-fluid">
    <a class="navbar-brand" href="#">&nbsp;<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-eye" viewBox="0 0 16 16">
  <path d="M16 8s-3-5.5-8-5.5S0 8 0 8s3 5.5 8 5.5S16 8 16 8M1.173 8a13 13 0 0 1 1.66-2.043C4.12 4.668 5.88 3.5 8 3.5s3.879 1.168 5.168 2.457A13 13 0 0 1 14.828 8q-.086.13-.195.288c-.335.48-.83 1.12-1.465 1.755C11.879 11.332 10.119 12.5 8 12.5s-3.879-1.168-5.168-2.457A13 13 0 0 1 1.172 8z"/>
  <path d="M8 5.5a2.5 2.5 0 1 0 0 5 2.5 2.5 0 0 0 0-5M4.5 8a3.5 3.5 0 1 1 7 0 3.5 3.5 0 0 1-7 0"/></svg>&nbsp;Brew (Fermentor) Tilt
    </a>
  </div>
</nav>
<div class="container my-5">
  <div class="row">
    <div class="col fs-2" id="brew" style="font-weight:bold;"></div>
  </div>
  <div class="row"><div class="col"><hr/></div></div>
  <div class="row">
    <div class="col fs-2"><b>Latest Reading:</b></div>
    <div class="col fs-2" id="dateTime"><?=$d?></div>
  </div>
  <div class="row"><div class="col"><hr/></div></div>
  <div class="row">
    <div class="col fs-2"><b>Gravity:</b></div>
    <div class="col fs-2" id="gravity"><?=$sg?></div>
  </div>
  <div class="row">
    <div class="col fs-2"><b>Tilt:</b></div>
    <div class="col fs-2" id="tilt"><?=$t_az?> (<?=$t?>)</div>
  </div>
  <div class="row">
    <div class="col fs-2"><b>Battery Voltage:</b></div>
    <div class="col fs-2" id="bv"><?=$bv?></div>
  </div>
  <div class="row"><div class="col"><hr/></div></div>
  <div class="row">
    <div class="col fs-2"><b>Roll:</b></div>
    <div class="col fs-2" id="roll"><?=$r?></div>
  </div>
  <div class="row">
    <div class="col fs-2"><b>Temperature:</b></div>
    <div class="col fs-2" id="Temp"><?=$T?></div>
  </div>
  <div class="row"><div class="col"><hr/></div></div>
</div>
<div class="container-fluid text-center my-5">
  <div class="row">
    <div class="col">
      <img src="tilt.png?t=<?=$s?>" alt="Tilt, Roll, Temp and Battery Voltage" />
    </div>
  </div>
</div>
</body>
</html>
<?php
}
?>
