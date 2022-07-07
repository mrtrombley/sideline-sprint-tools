<?php

// Load in packages.
session_start();
require_once "/var/www/html/secure/sprint-tools.php";
require_once "/var/www/html/secure/sprint-configuration.php";
require_once "/var/www/html/vendor/autoload.php";

use Auth0\SDK\Auth0;

// Handle errors sent back by Auth0.
if (!empty($_GET['error']) || !empty($_GET['error_description'])) {
  printf('<h1>Error</h1><p>%s</p>', htmlspecialchars($_GET['error_description']));
  die();
}

// Instantiate the base Auth0 class.
$auth0 = new Auth0([
  'domain' => $AUTH0_DOMAIN,
  'client_id' =>  $AUTH0_CLIENT_ID,
  'client_secret' => $AUTH0_CLIENT_SECRET,
  'redirect_uri' => $AUTH0_REDIRECT_URI,
]);

// Check for user info.
try {
  $userInfo = $auth0->getUser();
} catch (Exception $e) {
  die($e->getMessage());
}

// Handle flow based on authentication.
if (!$userInfo) {
  $request_uri = "https://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
  setcookie("SPRINT-ORIGIN", $request_uri, time() + 60 * 60, "/", "tools.sidelinesprint.com", true);
  header("Location: https://tools.sidelinesprint.com/login");
  exit();
} else {
  if ((!isset($_SESSION["user_data"])) || (empty($_SESSION["user_data"]))) {
    $staff_validation = validate_staff($userInfo["email"]);
    if ($staff_validation["status"] == "success") {
      $_SESSION["user_data"] = $staff_validation["data"];
    } else {
      header("Location: https://tools.sidelinesprint.com/error");
      exit();
    }
  }
  $user_email = $_SESSION["user_data"]["email"];
  $user_name = $_SESSION["user_data"]["name"];
  $user_profile_pic = $_SESSION["user_data"]["profile_picture"];
  $user_role = $_SESSION["user_data"]["role"];
  $user_internal_api_key = $_SESSION["user_data"]["internal_api_key"];
}

// Wrap in try block.
try {

  // Authenticate API.
  $cm_auth = array("api_key" => $cm_api_key);
  $cm_client_wrap = new CS_REST_Clients($cm_client_id, $cm_auth);

  // Get IDs of journeys.
  $cm_journey_result = $cm_client_wrap->get_journeys();
  if ($cm_journey_result->was_successful()) {
    $cm_journeys = $cm_journey_result->response;
  } else {
    throw new Exception("Error getting journey IDs from Campaign Monitor.");
  }

  // Get journey stats.
  $cm_journey_array = array();
  foreach ($cm_journeys as $journey) {
    $journey_id = $journey->JourneyID;
    $journey_list = $journey->ListID;
    $journey_name = $journey->Name;
    $journey_status = $journey->Status;
    $single_journey_wrap = new CS_REST_Journeys($journey_id, $cm_auth);
    $single_journey_result = $single_journey_wrap->get_journey_summary();
    if ($single_journey_result->was_successful()) {
      $single_journey_response = $single_journey_result->response;
      $single_journey_array = array(
        "name" => $journey_name,
        "id" => $journey_id,
        "list" => $journey_list,
        "status" => $journey_status,
        "email_id" => $single_journey_response->Emails[0]->EmailID,
        "opened" => number_format($single_journey_response->Emails[0]->Opened),
        "unique_opened" => number_format($single_journey_response->Emails[0]->UniqueOpened),
        "sent" => number_format($single_journey_response->Emails[0]->Sent),
        "unsubscribed" => number_format($single_journey_response->Emails[0]->Unsubscribed),
        "bounced" => number_format($single_journey_response->Emails[0]->Bounced),
        "clicked" => number_format($single_journey_response->Emails[0]->Clicked),
      );
      $single_journey_array["open_rate"] = round(100 * ($single_journey_array["unique_opened"] / $single_journey_array["sent"]), 2);
      $cm_journey_array[] = $single_journey_array;
    } else {
      throw new Exception("Error getting journey stats from Campaign Monitor.");
    }
  }

} catch (Exception $e) {

  // Redirect on error.
  server_alert($e->getMessage());
  header("Location: https://tools.sidelinesprint.com/", true, 303);
  exit();
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="utf-8">
  <title>Journey Overview | Sideline Sprint</title>
  <meta name="description" content="For development purposes only.">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <meta name="robots" content="noindex, nofollow">
  <link rel="apple-touch-icon" sizes="180x180" href="https://cdn-tools.sidelinesprint.com/img/apple-touch-icon.png">
  <link rel="icon" type="image/png" sizes="32x32" href="https://cdn-tools.sidelinesprint.com/img/favicon-32x32.png">
  <link rel="icon" type="image/png" sizes="16x16" href="https://cdn-tools.sidelinesprint.com/img/favicon-16x16.png">
  <link rel="manifest" href="https://cdn-tools.sidelinesprint.com/misc/site.webmanifest">
  <link rel="mask-icon" href="https://cdn-tools.sidelinesprint.com/img/safari-pinned-tab.svg" color="#67ca88">
  <link rel="shortcut icon" href="https://cdn-tools.sidelinesprint.com/img/favicon.ico">
  <meta name="msapplication-TileColor" content="#484848">
  <meta name="theme-color" content="#484848">
  <link rel="preload" href="https://cdn-tools.sidelinesprint.com/fonts/1Ptug8zYS_SKggPNyCMIT4ttDfCmxA.woff2" as="font" type="font/woff2" crossorigin>
  <link rel="preload" href="https://cdn-tools.sidelinesprint.com/fonts/1Ptug8zYS_SKggPNyC0IT4ttDfA.woff2" as="font" type="font/woff2" crossorigin>
  <link rel="preload" href="https://cdn-tools.sidelinesprint.com/img/tools-header.png" as="image">
  <link rel="preload" href="https://cdn-tools.sidelinesprint.com/css/bootstrap.min.css" as="style">
  <link rel="preload" href="https://cdn-tools.sidelinesprint.com/css/tools.min.css" as="style">
  <link rel="preload" href="https://cdn-tools.sidelinesprint.com/css/datatables.min.css" as="style">
  <link rel="stylesheet" href="https://cdn-tools.sidelinesprint.com/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdn-tools.sidelinesprint.com/css/tools.min.css">
  <link rel="stylesheet" href="https://cdn-tools.sidelinesprint.com/css/datatables.min.css">
  <script async src="https://cdn-tools.sidelinesprint.com/js/lazysizes.min.js"></script>
</head>

<body class="body-font">
  <?php include '/var/www/html/secure/navigation.php'; ?>
  <div class="jumbotron jumbotron-fluid jumbotron-sprint">
    <div class="container">
      <div class="row pb-1">
        <div class="col-lg-12 text-center pt-1">
          <img src="https://cdn-tools.sidelinesprint.com/img/logo_placeholder.png" data-src="https://cdn-tools.sidelinesprint.com/img/main_logo.png" alt="The Sideline Sprint logo." width="500" height="45" class="img-fluid lazyload pb-15">
          <h2 class="green-text bold-text pt-15">Journey Overview</h2>
        </div>
      </div>
    </div>
  </div>
  <div class="container">
    <div class="row text-center">
      <div class="col-lg-12">
        <div class="alert alert-danger" role="alert">
          <span>This tool is deprecated now that we have moved to Beehiiv. It is left up as a backup.</span>
        </div>
        <h3 class="green-text bold-text pt-15 pb-15">Journeys</h3>
        <table id="journey_table" class="table table-striped table-bordered nowrap" width="100%">
          <thead>
            <tr>
              <th scope="col">#</th>
              <th scope="col">Name</th>
              <th scope="col">Status</th>
              <th scope="col">Open Rate</th>
              <th scope="col">Sent</th>
              <th scope="col">Opened</th>
              <th scope="col">Unique Opened</th>
              <th scope="col">Unsubscribed</th>
              <th scope="col">Bounced</th>
              <th scope="col">Clicked</th>
              <th scope="col">Email ID</th>
              <th scope="col">Journey ID</th>
              <th scope="col">List ID</th>
            </tr>
          </thead>
          <tbody>
            <?php
            $row_id = 0;
            foreach ($cm_journey_array as $table_entry) {
              ++$row_id;
              echo "<tr>
                    <th scope=\"row\">{$row_id}</th>
                    <td>{$table_entry["name"]}</td>
                    <td>{$table_entry["status"]}</td>
                    <td>{$table_entry["open_rate"]}%</td>
                    <td>{$table_entry["sent"]}</td>
                    <td>{$table_entry["opened"]}</td>
                    <td>{$table_entry["unique_opened"]}</td>
                    <td>{$table_entry["unsubscribed"]}</td>
                    <td>{$table_entry["bounced"]}</td>
                    <td>{$table_entry["clicked"]}</td>
                    <td>{$table_entry["email_id"]}</td>
                    <td>{$table_entry["id"]}</td>
                    <td>{$table_entry["list"]}</td>
                    </tr>";
            } ?>
          </tbody>
        </table>
        <hr>
        <p class="footer"> &copy; Sideline Sprint 2021. All rights reserved.</p>
      </div>
    </div>
  </div>
  </div>
  <script src="https://cdn-tools.sidelinesprint.com/js/jquery-3.5.1.min.js"></script>
  <script src="https://cdn-tools.sidelinesprint.com/js/popper.min.js"></script>
  <script src="https://cdn-tools.sidelinesprint.com/js/bootstrap.min.js"></script>
  <script src="https://cdn-tools.sidelinesprint.com/js/datatables.min.js"></script>
  <script>
    $(document).ready(function() {

      // Get variables from authentication.
      var user_email = "<?php echo $user_email; ?>";
      var user_name = "<?php echo $user_name; ?>";
      var user_profile_pic = "<?php echo $user_profile_pic; ?>";
      var user_internal_api_key = "<?php echo $user_internal_api_key; ?>";

      // Load navigation.
      $("#internal-insights-link, #journey-manager-link").addClass("active");
      $("#internal-insights-link").html('<strong>' + $("#internal-insights-link").text() + '</strong>');
      $("#journey-manager-link").html('<strong class="white-font">' + $("#journey-manager-link").text() + '</strong> <span class="sr-only">(current)</span>');
      $("#navigation-placeholder").removeAttr("style");
      $("#name-link").text(user_name);
      $("#email-link").text(user_email);
      $("#user-pic").attr("data-src", user_profile_pic);
      $("#user-pic").attr("src", user_profile_pic);

      // Initialize datatable.
      $('#journey_table').DataTable({
        "scrollX": true,
        "pagingType": "full",
        "searching": true,
        "info": true,
        "language": {
          "emptyTable": "No information to show."
        }
      });

    });
  </script>
</body>

</html>
