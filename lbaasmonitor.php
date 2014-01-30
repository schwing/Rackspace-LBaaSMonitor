<?php

// $credsFile should be an ini file containing Rackspace Cloud credentials in this form:
//
//  [rackspace_cloud]
//  username = mycloudusername
//  api_key = myapikey
//
// Default is ~/.rackspace_cloud_credentials
$credsFile = $_SERVER['HOME'] . "/.rackspace_cloud_credentials";

// Declare the region for the servers and load balancer
$region = 'DFW';

// Declare the load balancer ID--this can be found on the load balancer details page
$lbId = '000000';

// Declare the interval, in seconds, to wait between reboot attempts. This provides time for the load
// balancer to add servers back to the pool after rebooting.
$waitInterval = 120;

// The file to save the last reboot timestamp into
$lastRebootFile = 'lastreboot';

////////////////////////////////
// Configuration ends here
//

require 'vendor/autoload.php';

use OpenCloud\Rackspace;

try {
    // Check the last reboot timestamp to prevent reboot loops
    if (is_file($lastRebootFile) && (file_get_contents($lastRebootFile) > (time() - $waitInterval))) {
        throw new Exception("Last reboot was completed less than " . $waitInterval . " seconds ago. Exiting.\n");
    }

    // Try to open $credsFile and read the credentials from it
    if (($cloudCreds = @parse_ini_file($credsFile)) == false) {
        throw new Exception("Missing or unreadable INI file: " . $credsFile . "\n");
    }

    // Auth using credentials from $credsFile
    $client = new Rackspace(Rackspace::US_IDENTITY_ENDPOINT, array(
        'username' => $cloudCreds['username'],
        'apiKey'   => $cloudCreds['api_key']
    ));

    $loadBalancers = $client->loadBalancerService('cloudLoadBalancers', $region);
    $compute = $client->computeService('cloudServersOpenStack', $region);

    // Create $serverMap to map ServiceNet IP addresses to UUIDs
    foreach ($servers = $compute->serverList() as $server) {
        $serverUuid = $server->id;
        $privateAddress = $server->addresses->private[0]->addr;
        $serverMap[$privateAddress] = $serverUuid;
    }

    // Fetch the load balancer object for the configured ID
    $lb = $loadBalancers->loadBalancer($lbId);

    $rebootedNode = FALSE;
    foreach ($lb->nodeList() as $node) {
        if ($node->condition == "ENABLED" && $node->status == "OFFLINE") {
            printf("Node %s (%s) Automatically Disabled: Forcing hard reboot of node.\n", $node->id, $node->address);
            // Reboot the unresponsive node using $serverMap and the private IP from the load balancer information
            $unServer = $compute->server($serverMap[$node->address]);
            // Make sure the server is in "ACTIVE" state
            if ($unServer->status == "ACTIVE") {
                // reboot() defaults to a hard reboot
                $reboot = $unServer->reboot();
            }
            $rebootedNode = TRUE;
        }
    }

    if ($rebootedNode == FALSE) {
        echo "No nodes rebooted.\n";
    } else {
        // Update the last reboot status file
        file_put_contents($lastRebootFile, time());
    }

} catch (Exception $e) {
    die($e->getMessage());
}

?>
