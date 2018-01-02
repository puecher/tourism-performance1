<?php

include_once 'settings.php';

error_reporting(0);
// @TODO: use a php framework

set_time_limit(0);

//requires php5-intl module to work
$formatter = new NumberFormatter('it', NumberFormatter::DECIMAL);

$con = mysqli_connect(SERVER, USERNAME, PASSWORD, DATABASE_NAME); // @TODO: change db storage engine

function random_date() {
  $hour = rand(0, 23);
  $minute = rand(0, 59);
  $second = rand(0, 59);
  $month = rand(1, 7);
  $day = rand(1, 31);
  $year = 2015;

  return mktime($hour, $minute, $second, $month, $day, $year);
}

$categories = array(
  1 => 'Gastgewerbliche Betriebe (1-3)',
  'Gastgewerbliche Betriebe (4-5)',
  'Privatvermieter',
  'Bauernhöfe',
  'Sonstiges',
  'Altro',
);
$categoriesColors = array(
  1 => '#dc3912',
  '#ff9900',
  '#109618',
  '#990099',
  '#0099c6',
  '#cccccc',
);

$countries = array();
$query = mysqli_query($con, "select distinct country from data where country != '' order by country");
while($row = mysqli_fetch_object($query)) {
  $countries[] = $row->country;
}

$gemeinden = array();
if (($handle = fopen("gemeinden-istat.csv", "r")) !== FALSE) {
  while (($data = fgetcsv($handle, 1000, ";")) !== FALSE) {
    $gemeinden[$data[1]] = $data[0];
  }
  fclose($handle);
}

$date_format1 = '%Y-%m';
$date_format2 = '%M %Y';
if ($date_format = $_POST['date_format']) {
  if ('week' == $date_format) {
    $date_format1 = '%Y-%w';
    $date_format2 = '%W %Y';
  } elseif ('month-week' == $date_format) {
    $date_format1 = '%Y-%m-%w';
    $date_format2 = '%W %M %Y';
  } elseif ('day' == $date_format) {
    $date_format1 = '%Y-%m-%d';
    $date_format2 = '%d %M %Y';
  }
}

function dummy_data() {
  global $categories;
  global $random_category;
  global $countries;
  global $gemeinden;

  $duration = rand(1, 7); // between 1 and 7 days
  $random_category = array_rand($categories);
  $random_country = array_rand($countries);
  $random_gemeinde = array_rand($gemeinden);

  $arrival = random_date();

  $data = array();
  $data['arrival'] = '"'.date('Y-m-d H:i:s', $arrival).'"';
  $data['departure'] = '"'.date('Y-m-d H:i:s', strtotime('+' . $duration . ' day', $arrival)).'"';
  $data['country'] = '"'.$countries[$random_country].'"';
  $data['adults'] = rand(1, 3);
  $data['children'] = rand(0, 7);
  $data['destination'] = '"'.$gemeinden[$random_gemeinde].'"';
  $data['category'] = '"'.$categories[$random_category].'"';
  $data['cancellation'] = 0;
  $data['created_at'] = '"'.date('Y-m-d H:i:s', rand(mktime(0, 0, 0, 1, 1, 2015), $arrival)).'"';

  return $data;
}

/*for($i = 0; $i < 100; $i++) { // insert dummy data
  $sql = "insert ignore into data values (".implode( ", ", dummy_data() ).")";
  mysql_unbuffered_query($sql);
}*/

$where = array(); // create where statement

if($arrival = $_POST['arrival']) {
  if($_a = explode(' - ', $arrival)) {
    $where[] = "arrival BETWEEN '".$_a[0]."' AND '".$_a[1]."'";
  } else {
    $where[] = "arrival = '".$arrival."'";
  }
}
if($departure = $_POST['departure']) {
  if($_d = explode(' - ', $departure)) {
    $where[] = "departure BETWEEN '".$_d[0]."' AND '".$_d[1]."'";
  } else {
    $where[] = "departure = '".$departure."'";
  }
}
if($country = $_POST['country']) {
  array_walk($country, '_addclashes');
  $where[] = "country IN (".implode(",",$country).")";
}
if($adults = $_POST['adults']) {
  if(is_numeric($adults)) {
    $where[] = "adults = '".$adults."'";
  } elseif($a = @explode("-", $adults)) {
    $adults1 = $a[0];
    $adults2 = $a[1];

    $where[] = "adults BETWEEN ".$adults1." AND ".$adults2;
  }
}
if($children = $_POST['children']) {
  if(is_numeric($children)) {
    $where[] = "children = '".$children."'";
  } elseif($a = @explode("-", $children)) {
    $children1 = $a[0];
    $children2 = $a[1];

    $where[] = "children BETWEEN ".$children1." AND ".$children2;
  }
}
if($destination = $_POST['destination']) {
  //array_walk($destination, '_addclashes');
  $where[] = "destination IN (".implode(",",$destination).")";
}
if($category = $_POST['category']) {
  //array_walk($category, '_addclashes');
  $where[] = "category IN (".implode(",",$category).")";
}
if($submitted_on = $_POST['submitted_on']) {
  if($_s = explode(' - ', $submitted_on)) {
    $where[] = "submitted_on BETWEEN '".$_s[0]."' AND '".$_s[1]."'";
  } else {
    $where[] = "submitted_on = '".$submitted_on."'";
  }
}
/*if($submitted_since = $_POST['submitted_since']) {
  $where[] = "submitted_on >= '".$submitted_since."'";
}
if($submitted_until = $_POST['submitted_until']) {
  $where[] = "submitted_on <= '".$submitted_until."'";
}*/
$enquiries_bookings_label = 'Enquiries and bookings';
if($enquiries_bookings = $_POST['enquiries_bookings']) {
  if(1 == $enquiries_bookings) {
    $enquiries_bookings_label = 'Enquiries';
    $where[] = "booking = 0";
  } elseif(2 == $enquiries_bookings) {
    $enquiries_bookings_label = 'Bookings';
    $where[] = "booking = 1";
  }
}
if($max_datediff = (int) $_POST['max_datediff']) {
  $where[] = "abs(datediff(arrival, departure)) <= ".$max_datediff;
}
if($max_adults = (int) $_POST['max_adults']) {
  $where[] = "adults <= ".$adults;
}
if($max_children = (int) $_POST['max_children']) {
  $where[] = "children <= ".$max_children;
}

function _addclashes(&$item1)
{
  $item1 = "'".$item1."'";
}

//var_dump($where); exit;

$query = mysqli_query($con, "select max(c) c_max, max(avg_adults) avg_adults_max, max(avg_children) avg_children_max, max(avg_stay) avg_stay_max from (select
          count(*) c,
          round(avg(adults), 3) avg_adults,
          round(avg(children), 3) avg_children,
          round(avg(abs(datediff(departure, arrival))), 3) avg_stay
          from data
          ".(count($where)>0?" where " . implode(" and ", $where):"")."
          group by date_format(arrival, '".$date_format1."')) a");
$max = mysqli_fetch_object($query);

?>
<!DOCTYPE html>
<html>
  <head>
    <meta charset="UTF-8">
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.5/css/bootstrap.min.css">
    <link rel="stylesheet" href="//cdn.jsdelivr.net/bootstrap.daterangepicker/2/daterangepicker.css">
    <script type="text/javascript" src="https://www.google.com/jsapi"></script>
    <script type="text/javascript">
      google.load("visualization", "1", {packages:["geochart", "corechart"], 'language': 'it'});
      google.setOnLoadCallback(charts);

      function charts() {

        var data = google.visualization.arrayToDataTable([
          ['Country', 'Number of <?php echo $enquiries_bookings_label; ?>']
          <?php $query = mysqli_query($con, "select country, count(country) c from data".(count($where)>0?" where " . implode(" and ", $where):"")." group by country"); //  order by c desc
          while($row = mysqli_fetch_object($query)) : ?>
            ,['<?php echo $row->country; ?>', <?php echo (float) $row->c; ?>]
          <?php endwhile ; ?>
        ]);

        var options = {};

        var chart = new google.visualization.GeoChart(document.getElementById('regions_div'));

        chart.draw(data, options);


        var data = google.visualization.arrayToDataTable([
          ['Date', 'Number of <?php echo $enquiries_bookings_label; ?>', 'Average Number of Adults', 'Average Number of Children', 'Average Length of Stay']
          <?php $query = mysqli_query($con, "select
            date_format(arrival, '".$date_format1."') yemo,
            date_format(arrival, '".$date_format2."') formatted,
            count(*) c,
            round(avg(adults), 3) avg_adults,
            round(avg(children), 3) avg_children,
            round(avg(abs(datediff(departure, arrival))), 3) avg_stay
            from data
            ".(count($where)>0?" where " . implode(" and ", $where):"")."
            group by yemo order by yemo asc");
          while($row = mysqli_fetch_object($query)) : ?>
            ,['<?php echo $row->formatted; ?>', <?php echo round((100*(float) $row->c)/(float) $max->c_max); ?>, <?php echo round((100*(float) $row->avg_adults)/(float) $max->avg_adults_max); ?>, <?php echo round((100*(float) $row->avg_children)/(float) $max->avg_children_max); ?>, <?php echo round((100*(float) $row->avg_stay)/(float) $max->avg_stay_max); ?>]
          <?php endwhile; ?>
        ]);

        var options = {
          title: '<?php echo $enquiries_bookings_label; ?> by Arrival Date',
          curveType: 'function',
            vAxis: {
              viewWindowMode: 'explicit',
              viewWindow: {
                min: 0,
                max: 100
              },
              format: '#\'%\''
            }
        };

        var chart = new google.visualization.LineChart(document.getElementById('curve_chart'));

        chart.draw(data, options);


        var data = google.visualization.arrayToDataTable([
          ['Date', 'Number of <?php echo $enquiries_bookings_label; ?>']
          <?php $query = mysqli_query($con, "select
            date_format(departure, '".$date_format1."') yemo,
            date_format(departure, '".$date_format2."') formatted,
            count(*) c
            from data
            ".(count($where)>0?" where " . implode(" and ", $where):"")."
            group by yemo order by yemo asc");
          while($row = mysqli_fetch_object($query)) : ?>
            ,['<?php echo $row->formatted; ?>', <?php echo (100*(float) $row->c)/(float) $max->c_max; ?>]
          <?php endwhile; ?>
        ]);

        var options = {
          title: '<?php echo $enquiries_bookings_label; ?> by Departure Date',
          curveType: 'function',
          vAxis: {
            viewWindowMode: 'explicit',
            viewWindow: {
              min: 0,
              max: 100
            },
            format: '#\'%\''
          }
        };

        var chart = new google.visualization.LineChart(document.getElementById('curve_chart3'));

        chart.draw(data, options);


        var data = google.visualization.arrayToDataTable([
          ['Date', 'Number of <?php echo $enquiries_bookings_label; ?>', 'Average Number of Adults', 'Average Number of Children', 'Average Length of Stay']
          <?php $query = mysqli_query($con, "select
            date_format(submitted_on, '".$date_format1."') yemo,
            date_format(submitted_on, '".$date_format2."') formatted,
            count(*) c,
            round(avg(adults), 3) avg_adults,
            round(avg(children), 3) avg_children,
            round(avg(abs(datediff(departure, arrival))), 3) avg_stay
            from data
            ".(count($where)>0?" where " . implode(" and ", $where):"")."
            group by yemo order by yemo asc");
          while($row = mysqli_fetch_object($query)) : ?>
            ,['<?php echo $row->formatted; ?>', <?php echo round((100*(float) $row->c)/(float) $max->c_max); ?>, <?php echo round((100*(float) $row->avg_adults)/(float) $max->avg_adults_max); ?>, <?php echo round((100*(float) $row->avg_children)/(float) $max->avg_children_max); ?>, <?php echo round((100*(float) $row->avg_stay)/(float) $max->avg_stay_max); ?>]
          <?php endwhile; ?>
        ]);

        var options = {
          title: '<?php echo $enquiries_bookings_label; ?> by Submit Date',
          curveType: 'function',
          vAxis: {
            viewWindowMode: 'explicit',
            viewWindow: {
              //max: 180,
              min: 0,
              max: 100
            },
            format: '#\'%\''
          }
        };

        var chart = new google.visualization.LineChart(document.getElementById('curve_chart2'));

        chart.draw(data, options);


        var data = google.visualization.arrayToDataTable([
          ['Category', 'Number of <?php echo $enquiries_bookings_label; ?>']
          <?php $slicesColors = array();
          $query = mysqli_query($con, "select category, count(*) c from data".(count($where)>0?" where " . implode(" and ", $where):"")." group by category");
          while($row = mysqli_fetch_object($query)) : $slicesColors[] = $categoriesColors[$row->category]; ?>
            ,['<?php echo $categories[$row->category]; ?>', <?php echo (float) $row->c; ?>]
          <?php endwhile; ?>
        ]);

        var options = {
          title: 'Categories',
          pieHole: 0.4,
          slices: {
            <?php foreach ( $slicesColors as $key => $color ) echo $key . ": { color: '" . $color . "' },"; ?>
          }
        };

        var chart = new google.visualization.PieChart(document.getElementById('donutchart'));
        chart.draw(data, options);


        var data = google.visualization.arrayToDataTable([
          ['Destination', 'Number of <?php echo $enquiries_bookings_label; ?>']
          <?php $query = mysqli_query($con, "select destination, count(*) c from data".(count($where)>0?" where " . implode(" and ", $where):"")." group by destination order by c desc");
          while($row = mysqli_fetch_object($query)) : ?>
            ,['<?php echo $gemeinden[$row->destination]; ?>', <?php echo (float) $row->c; ?>]
          <?php endwhile; ?>
        ]);

        var options = {
          title: 'Destinations',
          pieHole: 0.4,
        };

        var chart = new google.visualization.PieChart(document.getElementById('donutchart2'));
        chart.draw(data, options);


        var data = google.visualization.arrayToDataTable([
          ['Day of Week', 'Number of <?php echo $enquiries_bookings_label; ?>']
          <?php $query = mysqli_query($con, "select date_format(arrival, '%w') day_of_week, date_format(arrival, '%W') weekday_name, count(*) c from data".(count($where)>0?" where " . implode(" and ", $where):"")." group by day_of_week order by c desc");
          while($row = mysqli_fetch_object($query)) : ?>
            ,['<?php echo $row->weekday_name; ?>', <?php echo (float) $row->c; ?>]
          <?php endwhile; ?>
        ]);

        var options = {
          title: 'Most Arrival Day of Week',
          pieHole: 0.4,
        };

        var chart = new google.visualization.PieChart(document.getElementById('donutchart3'));
        chart.draw(data, options);


        var data = google.visualization.arrayToDataTable([
          ['Day of Week', 'Number of <?php echo $enquiries_bookings_label; ?>']
          <?php $query = mysqli_query($con, "select date_format(departure, '%w') day_of_week, date_format(departure, '%W') weekday_name, count(*) c from data".(count($where)>0?" where " . implode(" and ", $where):"")." group by day_of_week order by c desc");
          while($row = mysqli_fetch_object($query)) : ?>
            ,['<?php echo $row->weekday_name; ?>', <?php echo (float) $row->c; ?>]
          <?php endwhile; ?>
        ]);

        var options = {
          title: 'Most Departure Day of Week',
          pieHole: 0.4,
        };

        var chart = new google.visualization.PieChart(document.getElementById('donutchart4'));
        chart.draw(data, options);


        <?php $query = mysqli_query($con, "SELECT max(c) max FROM (SELECT count(*) c FROM `data` ".(count($where)>0?" where " . implode(" and ", $where):"")." group by country) a");
        $max_by_country = mysqli_fetch_object($query); ?>
        var data = google.visualization.arrayToDataTable([
          //['ID', 'Life Expectancy', 'Fertility Rate', 'Region',     'Population']
          ['ID', 'Average Length of Stay', 'Number of <?php echo $enquiries_bookings_label; ?>', 'Average number of Adults', 'Average number of Children']
          <?php $query = mysqli_query($con, "SELECT
          a.country,
          (SELECT count(*) FROM data where country = a.country".(count($where)>0?" and " . implode(" and ", $where):"").") enquiries,
          (SELECT round(avg(abs(datediff(arrival, departure))), 3) FROM data WHERE country = a.country".(count($where)>0?" and " . implode(" and ", $where):"").") avg_days,
          (SELECT avg(adults) FROM data where country = a.country".(count($where)>0?" and " . implode(" and ", $where):"").") adults,
          (SELECT avg(children) FROM data where country = a.country".(count($where)>0?" and " . implode(" and ", $where):"").") children
          FROM `data` a
          group by a.country");
          while($row = mysqli_fetch_object($query)) : ?>
            ,['<?php echo $row->country; ?>', <?php echo (float) $row->avg_days; ?>, <?php echo round((100*(float) $row->enquiries)/$max_by_country->max); ?>, <?php echo (float) $row->children; ?>, <?php echo (float) $row->adults; ?>]
          <?php endwhile; ?>
          //,['CAN',    80.66,              1.67,      'North America',  33739900]
        ]);

        var options = {
          title: 'Average Length of Stay and number of <?php echo $enquiries_bookings_label; ?>',
          hAxis: {title: 'Average Length of Stay'},
          vAxis: {
            title: 'Number of <?php echo $enquiries_bookings_label; ?>',
            viewWindow: {
              min: 0,
              max: 100
            },
            format: '#\'%\''
          },
          bubble: {textStyle: {fontSize: 11}}
        };

        var chart = new google.visualization.BubbleChart(document.getElementById('series_chart_div'));
        chart.draw(data, options);


        var data = google.visualization.arrayToDataTable([
          ['ID', 'Number of Adults', 'Number of Children']
          <?php $query = mysqli_query($con, "SELECT
          a.country,
          (SELECT round(avg(adults), 3) FROM data where country = a.country".(count($where)>0?" and " . implode(" and ", $where):"").") avg_adults,
          (SELECT round(avg(children), 3) FROM data WHERE country = a.country".(count($where)>0?" and " . implode(" and ", $where):"").") avg_children
          FROM `data` a
          group by a.country");
          while($row = mysqli_fetch_object($query)) : ?>
            ,['<?php echo $row->country; ?>', <?php echo (float) $row->avg_adults; ?>, <?php echo (float) $row->avg_children; ?>]
          <?php endwhile; ?>
        ]);

        var options = {
          title: 'Number of Adults and number of Children',
          hAxis: {title: 'Number of Adults'},
          vAxis: {title: 'Number of Children'},
          bubble: {textStyle: {fontSize: 11}}
        };

        var chart = new google.visualization.BubbleChart(document.getElementById('series_chart_div2'));
        chart.draw(data, options);


        <?php $query = mysqli_query($con, "SELECT max(c) max FROM (SELECT count(*) c FROM `data` ".(count($where)>0?" where " . implode(" and ", $where):"")." group by destination) a");
        $max_by_destination = mysqli_fetch_object($query); ?>
        var data = google.visualization.arrayToDataTable([
          ['ID', 'Average Length of Stay', 'Number of <?php echo $enquiries_bookings_label; ?>', 'Average number of Adults', 'Average number of Children']
          <?php $query = mysqli_query($con, "SELECT
          a.destination,
          (SELECT count(*) FROM data where destination = a.destination".(count($where)>0?" and " . implode(" and ", $where):"").") enquiries,
          (SELECT round(avg(abs(datediff(arrival, departure))), 3) FROM data WHERE destination = a.destination".(count($where)>0?" and " . implode(" and ", $where):"").") avg_days,
          (SELECT avg(adults) FROM data where destination = a.destination".(count($where)>0?" and " . implode(" and ", $where):"").") adults,
          (SELECT avg(children) FROM data where destination = a.destination".(count($where)>0?" and " . implode(" and ", $where):"").") children
          FROM `data` a
          group by a.destination");
          while($row = mysqli_fetch_object($query)) : ?>
            ,['<?php echo $gemeinden[$row->destination]; ?>', <?php echo (float) $row->avg_days; ?>, <?php echo round((100*(float) $row->enquiries)/$max_by_destination->max); ?>, <?php echo (float) $row->children; ?>, <?php echo (float) $row->adults; ?>]
          <?php endwhile; ?>
        ]);

        var options = {
          title: 'Average Length of Stay and number of <?php echo $enquiries_bookings_label; ?>',
          hAxis: {title: 'Average Length of Stay'},
          vAxis: {
            title: 'Number of <?php echo $enquiries_bookings_label; ?>',
            viewWindow: {
              min: 0,
              max: 100
            },
            format: '#\'%\''
          },
          bubble: {textStyle: {fontSize: 11}}
        };

        var chart = new google.visualization.BubbleChart(document.getElementById('series_chart_div3'));
        chart.draw(data, options);


        var data = google.visualization.arrayToDataTable([
          ['ID', 'Number of Adults', 'Number of Children']
          <?php $query = mysqli_query($con, "SELECT
          a.destination,
          (SELECT round(avg(adults), 3) FROM data where destination = a.destination".(count($where)>0?" and " . implode(" and ", $where):"").") avg_adults,
          (SELECT round(avg(children), 3) FROM data WHERE destination = a.destination".(count($where)>0?" and " . implode(" and ", $where):"").") avg_children
          FROM `data` a
          group by a.destination");
          while($row = mysqli_fetch_object($query)) : ?>
            ,['<?php echo $gemeinden[$row->destination]; ?>', <?php echo (float) $row->avg_adults; ?>, <?php echo (float) $row->avg_children; ?>]
          <?php endwhile; ?>
        ]);

        var options = {
          title: 'Number of Adults and number of Children',
          hAxis: {title: 'Number of Adults'},
          vAxis: {title: 'Number of Children'},
          bubble: {textStyle: {fontSize: 11}}
        };

        var chart = new google.visualization.BubbleChart(document.getElementById('series_chart_div4'));
        chart.draw(data, options);
      }
    </script>
    <style>
    body {
      font: 18px/1.5 sans-serif;
    }
    .special-wrapper {
      float: left;
      width: 33.33%;
    }
    .special {
      margin: 16px 10px;
      padding: 24px 24px 24px 72px;
      background-color: #039be5;
      color: #fff;
    }
    h2 {
      border-bottom: 1px solid #ebebeb;
      font: 400 24px/32px Roboto,sans-serif;
      letter-spacing: -0.01em;
      margin: 40px 0 20px;
      padding-bottom: 3px;
    }
    .special h2:first-child {
      margin-top: 0;
    }
    .special p:last-child {
      margin-bottom: 0;
    }
    </style>
  </head>
  <body>
    <div class="container-fluid">
      <h1>Data analyzer</h1>
      <form action="" method="post">
        <div class="row">
          <div class="col-sm-3">
            <div class="form-group">
              <label for="date_format">Granularity</label>
              <select class="form-control" name="date_format" id="date_format">
                <option<?php if ( 'month' == $_POST['date_format'] ) echo ' selected="selected"'; ?>>month</option>
                <option<?php if ( 'month-week' == $_POST['date_format'] ) echo ' selected="selected"'; ?>>month-week</option>
                <option<?php if ( 'week' == $_POST['date_format'] ) echo ' selected="selected"'; ?>>week</option>
                <option<?php if ( 'day' == $_POST['date_format'] ) echo ' selected="selected"'; ?>>day</option>
              </select>
            </div>
          </div>
          <div class="col-sm-3">
            <div class="form-group">
              <label for="arrival">Arrival</label>
              <input type="text" class="form-control datepicker" name="arrival" id="arrival" placeholder="YYYY-MM-DD" value="<?php echo $_POST['arrival']; ?>">
            </div>
          </div>
          <div class="col-sm-3">
            <div class="form-group">
              <label for="arrival">Departure</label>
              <input type="text" class="form-control datepicker" name="departure" id="departure" placeholder="YYYY-MM-DD" value="<?php echo $_POST['departure']; ?>">
            </div>
          </div>
          <div class="col-sm-3">
            <div class="form-group">
              <label for="country">Country</label>
              <select multiple class="form-control" id="country" name="country[]">
                <?php foreach($countries as $country) : ?>
                  <option value="<?php echo $country; ?>"<?php if ( in_array( $country, (array) $_POST['country'] ) ) echo ' selected="selected"'; ?>><?php echo $country; ?></option>
                <?php endforeach ; ?>
              </select>
            </div>
          </div>
          <div class="col-sm-3">
            <div class="form-group">
              <label for="adults">Adults</label>
              <input type="text" class="form-control" name="adults" id="adults" placeholder="e.g. 2 or 3-5" value="<?php echo $_POST['adults']; ?>">
            </div>
          </div>
          <div class="col-sm-3">
            <div class="form-group">
              <label for="children">Children</label>
              <input type="text" class="form-control" name="children" id="children" placeholder="e.g. 3 or 2-5" value="<?php echo $_POST['children']; ?>">
            </div>
          </div>
          <div class="col-sm-3">
            <div class="form-group">
              <label for="destination">Destination</label>
              <select multiple class="form-control" id="destination" name="destination[]">
                <?php foreach($gemeinden as $key => $destination) : ?>
                  <option value="<?php echo $key; ?>"<?php if ( in_array( $key, (array) $_POST['destination'] ) ) echo ' selected="selected"'; ?>><?php echo $destination; ?></option>
                <?php endforeach ; ?>
              </select>
            </div>
          </div>
          <div class="col-sm-3">
            <div class="form-group">
              <label for="category">Category</label>
              <select multiple class="form-control" id="category" name="category[]">
                <?php foreach($categories as $key => $category) : ?>
                  <option value="<?php echo $key; ?>"<?php if ( in_array( $key, (array) $_POST['category'] ) ) echo ' selected="selected"'; ?>><?php echo $category; ?></option>
                <?php endforeach ; ?>
              </select>
            </div>
          </div>
          <div class="col-sm-3">
            <div class="form-group">
              <label for="children">Submitted On</label>
              <input type="text" class="form-control datepicker" name="submitted_on" id="submitted_on" placeholder="YYYY-MM-DD" value="<?php echo $_POST['submitted_on']; ?>">
            </div>
          </div>
          <div class="col-sm-3">
            <div class="form-group">
              <label for="enquiries_bookings">Enquiries / Bookings</label>
              <select class="form-control" id="enquiries_bookings" name="enquiries_bookings">
                <option value=""></option>
                <option value="1"<?php if ( 1 == $_POST['enquiries_bookings'] ) echo ' selected="selected"'; ?>>Enquiries</option>
                <option value="2"<?php if ( 2 == $_POST['enquiries_bookings'] ) echo ' selected="selected"'; ?>>Bookings</option>
              </select>
            </div>
          </div>
          <div class="col-sm-3">
            <div class="form-group">
              <label for="max_datediff">Max datediff(arrival, departure) in Days (<=)</label>
              <input type="text" class="form-control datepicker" name="max_datediff" id="max_datediff" value="<?php echo $_POST['max_datediff']; ?>">
            </div>
          </div>
          <div class="col-sm-3">
            <div class="form-group">
              <label for="max_adults">Max Adults (<=)</label>
              <input type="text" class="form-control" name="max_adults" id="max_adults" value="<?php echo $_POST['max_adults']; ?>">
            </div>
          </div>
          <div class="col-sm-3">
            <div class="form-group">
              <label for="max_children">Max Children (<=)</label>
              <input type="text" class="form-control" name="max_children" id="max_children" value="<?php echo $_POST['max_children']; ?>">
            </div>
          </div>
        </div>
        <button type="submit" class="btn btn-default">Submit</button>
      </form>
    </div>

    <div id="regions_div" style="width: 100%; height: 800px;"></div>
    <div id="curve_chart" style="width: 100%; height: 800px"></div>
    <div id="curve_chart3" style="width: 100%; height: 800px"></div>
    <div id="curve_chart2" style="width: 100%; height: 800px"></div>
    <div id="donutchart" style="width: 100%; height: 800px;"></div>
    <div id="donutchart2" style="width: 100%; height: 800px;"></div>
    <div id="donutchart3" style="width: 100%; height: 800px;"></div>
    <div id="donutchart4" style="width: 100%; height: 800px;"></div>
    <div id="series_chart_div" style="width: 100%; height: 800px;"></div>
    <div id="series_chart_div2" style="width: 100%; height: 800px;"></div>
    <div id="series_chart_div3" style="width: 100%; height: 800px;"></div>
    <div id="series_chart_div4" style="width: 100%; height: 800px;"></div>

    <?php

    $query = mysqli_query($con, "select round(avg(abs(datediff(departure, arrival))), 3) avg_stay from data".(count($where)>0?" where " . implode(" and ", $where):""));
    $row = mysqli_fetch_object($query);
    ?>
    <div class="special-wrapper">
    <div class="special">
      <h2 class="noborder nomargin" id="infographics-usage-policy">Average Length of Stay</h2>
      <p><?php echo $formatter->format($row->avg_stay); ?> days</p>
    </div>
    </div>
    <?php

    $query = mysqli_query($con, "select round(avg(adults), 3) avg_adults from data".(count($where)>0?" where " . implode(" and ", $where):""));
    $row = mysqli_fetch_object($query);

    ?>
    <div class="special-wrapper">
    <div class="special">
      <h2 class="noborder nomargin" id="infographics-usage-policy">Average Number of Adults</h2>
      <p><?php echo $formatter->format($row->avg_adults); ?> adults</p>
    </div>
    </div>
    <?php

    $query = mysqli_query($con, "select round(avg(children), 3) avg_children from data".(count($where)>0?" where " . implode(" and ", $where):""));
    $row = mysqli_fetch_object($query);

    ?>
    <div class="special-wrapper">
    <div class="special">
      <h2 class="noborder nomargin" id="infographics-usage-policy">Average Number of Children</h2>
      <p><?php echo $formatter->format($row->avg_children); ?> children</p>
    </div>
    </div>

    <script src="https://code.jquery.com/jquery-2.1.4.min.js"></script>
    <script src="//cdn.jsdelivr.net/momentjs/latest/moment.min.js"></script>
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.5/js/bootstrap.min.js"></script>
    <script src="//cdn.jsdelivr.net/bootstrap.daterangepicker/2/daterangepicker.js"></script>
    <script>
    jQuery(function($) {
      $('.datepicker').daterangepicker({
        autoUpdateInput: false,
        locale: {
          cancelLabel: 'Clear',
          format: 'YYYY-MM-DD'
        }
      });
      $('.datepicker').on('apply.daterangepicker', function(ev, picker) {
        $(this).val(picker.startDate.format('YYYY-MM-DD') + ' - ' + picker.endDate.format('YYYY-MM-DD'));
      });
      $('.datepicker').on('cancel.daterangepicker', function(ev, picker) {
        $(this).val('');
      });
    });
    </script>

  </body>
</html>
