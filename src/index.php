<?php
define('TIMEZONE', 'Europe/Copenhagen');
define('ZENITH', 90.83); /* 90+(50/70) - The zenith angle we define as the moment the sunrise/sunset occurs. */
date_default_timezone_set(TIMEZONE);

class DegreeMath { 
    /**
     * Class containing convenience methods for trigonometry using degrees
     * instead of radians.
     */ 
    static function sin($n) {
        return sin(deg2rad($n));
    }
    static function cos($n) {
        return cos(deg2rad($n));
    }
    static function tan($n) {
        return tan(deg2rad($n));
    }
    static function asin($n) {
        return rad2deg(asin($n));
    }
    static function acos($n) {
        return rad2deg(acos($n));
    }
    static function atan($n) {
        return rad2deg(atan($n));
    }

}

class Location {
    /**
     * Class representing a location by coordinates.
     * @var latitude
     * @var longitude
     */

    function __construct($latitude, $longitude) {
        $this->latitude = $latitude;
        $this->longitude = $longitude;
    }

    static function get_offset($date) {
        /**
         * Get the local offset for TIMEZONE for the given date.
         * @var date The date
         */
        $tz = new DateTimeZone(TIMEZONE); 
        $transition = $tz->getTransitions($date, $date); 
        return $transition[0]['offset'] / 3600;
    }

    private function _calculate_sun_movement($date, $sunrise=true) {
        /**
         * Calculate the sunrise or sunset time for the given date.
         * Algorithm source: http://williams.best.vwh.net/sunrise_sunset_algorithm.htm
         * @var date The date
         * @var sunrise Boolean indicating whether we should calculate the
         *              sunrise, otherwise we calculate the sunset.
         */

        /* Step 1 */
        $n = date('z', $date)+1;

        /* Step 2 */
        $lngHour = $this->longitude / 15;
        if($sunrise) {
            $t = $n + ((6 - $lngHour) / 24);
        } else {
            $t = $n + ((18 - $lngHour) / 24);
        }

        /* Step 3 */
        $m = (0.9856 * $t) - 3.289;

        /* Step 4 */
        $l = $m + (1.916 * DegreeMath::sin($m)) + (0.02 * DegreeMath::sin(2 * $m)) + 282.634;

        if($l > 360) $l -= 360;
        if($l < 0) $l += 360;

        /* Step 5a */
        $ra = DegreeMath::atan(0.91764 * DegreeMath::tan($l));
        if($ra > 360) $ra -= 360;
        if($ra < 0) $ra += 360;

        /* Step 5b */
        $lq = (floor($l/90)) * 90;
        $raq = (floor($ra/90)) * 90;
        $ra = $ra + ($lq - $raq);

        /* Step 5c */
        $ra = $ra / 15;

        /* Step 6 */
        $sindec = 0.39782 * DegreeMath::sin($l);
        $cosdec = DegreeMath::cos(DegreeMath::asin($sindec));

        /* Step 7a */
        $cosh = (DegreeMath::cos(ZENITH) - ($sindec * DegreeMath::sin($this->latitude))) / ($cosdec * DegreeMath::cos($this->latitude));

        /* Step 7b */
        if($sunrise) {
            $h = 360 - DegreeMath::acos($cosh);
        } else {
            $h = DegreeMath::acos($cosh);
        }
        $h = $h / 15;

        /* Step 8 */
        $t = $h + $ra - (0.06571 * $t) - 6.622;

        /* Step 9 */
        $ut = $t - $lngHour;
        if($ut > 24) $ut -= 24;
        if($ut < 0) $ut += 24;

        /* Step 10 */
        $local = $ut + Location::get_offset($date);
        return $local;

    }

    function calculate_sunrise($date) {
        return $this->_calculate_sun_movement($date);
    }

    function calculate_sunset($date) {
        return $this->_calculate_sun_movement($date, false);
    }

}

function double_to_time($double, $as_array=false) {
    /**
     * Convert a double value to an HH:MM time representation or an array
     * with the same information.
     * @var double The value to convert
     * @var as_array Whether to return an array instead of a string
     */
    $hour = (int)floor($double);
    $minute = floor(60*($double - $hour));
    if($as_array) {
        return [$hour, $minute];
    } else {
        return sprintf('%02d:%02d', $hour, $minute);
    }
}

/* Location settings hardcoded for now */
$city = 'Århus, Denmark';
$latitude = 56.09;
$longitude = 10.11;

/* Read year and week from _GET, and fall back to current. */
$year = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');
$week = isset($_GET['week']) ? (int)$_GET['week'] : date('W');

/* As the assignment only covers showing weeks for this year and the next,
 * let's refuse other years than that. In reality, anything between 0 and
 * 32767 is valid for PHP. */ 
if($year != date('Y') && $year != date('Y')+1)
    $year = date('Y');

/* Allow valid week numbers only */
if(0 > $week || $week > 53)
    $week = date('W');

?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <title>Sunrise Calculator</title>
    <link href="bootstrap.css" rel="stylesheet">
    <style>
      body {
        padding-top: 30px;
      }
    </style>
  </head>
  <body>

    <div class="container">
 
      <h1>Location</h1>
        <table class="table table-bordered">
          <tbody>
            <tr>
              <th>Name</th>
              <td><?php print($city); ?></td>
            </tr>
            <tr>
              <th>Latitude</th>
              <td><?php print($latitude); ?></td>
            </tr>
            <tr>
              <th>Longitude</th>
              <td><?php print($longitude); ?></td>
            </tr>
          </tbody>
        </table>

      <h1>Week Selector</h1>
      <?php
      /* Calculate and print 52 week numbers, starting from the current */
      for($i = 0 ; $i < 52 ; $i++) {
          $ldate = time()+($i * 60 * 60 * 24 * 7);
          $lweek = date('W', $ldate);
          $lyear = date('Y', $ldate);
          $repr = ($lweek == $week && $lyear == $year) ? '<b>'.$lweek.'</b>' : $lweek;
          printf('<a href="?week=%s&year=%s">%s</a> ', $lweek, $lyear, $repr);
      }
      ?>

      <br /><br />
      <h1><?php printf('Year %s, week %s', $year, $week); ?></h1>
      <table class="table table-bordered">
        <thead>
          <tr>
            <th>Date</th>
            <th>Sunrise</th>
            <th>Sunset</th>
            <th>Day Length</th>
          </tr>
        </thead>
        <tbody>
      <?php

      /* Use week and day-of-week for January 1st to calculate monday in the wanted week. */
      $jan1 = mktime(0, 0, 0, 1, 1, $year);
      $jan1week = date('w', $jan1);
      $jan1day = date('N', $jan1);
      $start = (($week - $jan1week) * 7) - $jan1day + 2;

      for($i = 0 ; $i <= 6 ; $i++) {
          $date = mktime(0, 0, 0, 1, $start, $year) + ($i * 60 * 60 * 24);
          $location = new Location($latitude, $longitude);
          $sunrise = $location->calculate_sunrise($date);
          $sunset = $location->calculate_sunset($date);
          $interval = double_to_time($sunset-$sunrise, true);
      ?>
          <tr>
            <td><?php print(date('l, j. F', $date)); ?></td>
            <td><?php print(double_to_time($sunrise)); ?></td>
            <td><?php print(double_to_time($sunset)); ?></td>
            <td><?php printf('%s hours, %s minutes', $interval[0], $interval[1]); ?></td>
          </tr>
      <?php } ?>
        </tbody>
      </table>

      <hr />
    </div>

  </body>
</html>
