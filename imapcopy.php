#!/usr/bin/php
<?php

        // IMAP Migration Script - built from scratch to use new IMAP copy tool - JM 15/08/24
        // Uses php7.4 / 8.2
        // Requires use of imapsync by Gilles, see imapsync.lamiral.info or https://github.com/imapsync/imapsync
        // Make sure binary below is pointed correctly
        $logfolder = "migrationlogs";
        $imapsyncbinary = "/imapsync/imapsync-master/imapsync";

        // No touchy below
        $logpath = dirname(__FILE__) . "/$logfolder/";
        $dstserver = "mail.poxydoxy.com:993"; // Set this once, what ever your main server is
        $logbuffer = ""; // Store echos for final log file

        // Create logging directory
        if (!is_dir($logpath)) {
                echo2("Logging folder does not exist, creating...");
                mkdir($logpath, 0775, true);
        }

        if(isset($argv[1])){ $srcserver = $argv[1]; } else { showsyntax(); }
        if(isset($argv[2])){ $userfile = $argv[2]; } else { showsyntax(); }
        function showsyntax(){
                echo "PoxyDoxy IMAP Copy Script Usage:\n";
                echo "./imapcopy.php SourceServerIP:993 userlist.txt\n\n";
                echo "Userlist format:\n";
                echo "\$srcemail \$srcpass (if destination is the same)\n";
                echo "or\n";
                echo "\$srcemail \$srcpass \$dstemail \$dstpass (if destination is different)\n";
                echo "Multiple accounts on new line\n\n";
                exit();
        }
        $userlist = @file($userfile);
        if (!$userlist){ die("Could not read user list :(\n"); }

        // Build user list
        $users = array();
        foreach($userlist as $userline) {
                //var_dump($userline);
                //var_dump(explode(" ", trim($userline)));

                $userline = explode(" ", trim($userline));
                //var_dump(count($userline));
                if(count($userline) < 2){ die("Account file is empty!\n"); }
                if(isset($userline[0])){ $srcuser = $userline[0]; } else { die("Provide source username\n"); }
                if(isset($userline[1])){ $srcpass = $userline[1]; } else { die("Provide source password\n"); }
                if(isset($userline[2])){ $dstuser = $userline[2]; } else { $dstuser = $srcuser;}
                if(isset($userline[3])){ $dstpass = $userline[3]; } else { $dstpass = $srcpass;}

                $user["srcuser"] = $srcuser;
                $user["srcpass"] = $srcpass;
                $user["dstuser"] = $dstuser;
                $user["dstpass"] = $dstpass;

                // Check usernames are emails
                if(!filter_var($srcuser, FILTER_VALIDATE_EMAIL)) { die("Source user is not valid email! ($srcuser)\n"); }
                if(!filter_var($dstuser, FILTER_VALIDATE_EMAIL)) { die("Destination user is not valid email! ($dstuser)\n"); }

                // Check passwords
                if($srcpass == ""){ die("Source pass is not valid!\n"); }
                if($dstpass == ""){ die("Source pass is not valid!\n"); }

                array_push($users, $user);
        }

        $usercount = count($users);
        $usercountstring = ""; if($usercount > 1){ $usercountstring = "s"; }

        echo2("=========== POXYDOXY IMAP MIGRATION SCRIPT ===========\n");
        echo2("=> FROM: $srcserver TO: $dstserver\n");
        echo2("=> $usercount account$usercountstring detected\n");
        foreach($users as $user) {
                //var_dump($user);
                if($user['srcuser'] == $user['dstuser']){
                        echo2("=> from {$user['srcuser']} to itself\n");
                } else {
                        echo2("=> from {$user['srcuser']} to {$user['dstuser']}\n");
                }
        }
        echo2("====\n");
        echo2("Press Y/y to proceed: ");
        $check = trim(fgets( STDIN ));
        if(strtolower($check) != "y"){ die("Did not press Y/y! Stopping.\n");}

        if(count($users) < 1){ die("Detected 0 users in file, please specify at least one."); }

        // TIME TO COPY, WOOOOOO
        echo2("Running imapsync to migrate accounts\n");
        // Copy each user one at a time, the IMAP server should be maxed out by a single copy
        // Also don't want to be fail2banned or crash the remote server
        // The new imapcopy tool seems to ratelimit/throttle itself very well anyway
        foreach($users as $user) {
                $command = "$imapsyncbinary ";
                $command .= "--host1 $srcserver --user1 {$user['srcuser']} --password1 \"{$user['srcpass']}\" ";
                $command .= "--host2 $dstserver --user2 {$user['dstuser']} --password2 \"{$user['dstpass']}\" ";
                $command .= "--automap"; // The magic
                //echo2("$command\n");
                echo2("Running sync for {$user['srcuser']}...");
                $output = "";
                exec($command, $output);

                //var_dump($output);
                echo2("done\n");

                $outputlatch = false;
                foreach ($output as $line){
                        if($line == "++++ Statistics"){ $outputlatch = true; }
                        if($outputlatch){ echo2("$line\n"); }
                }
        }
        echo2("All syncs finished\n");

        // Store logs to final file
        $logfullpath = $logpath . date('Y_m_d_H_i_s') . "_" . preg_replace("/[^A-Za-z0-9\.]/", '',  $userfile);
        file_put_contents($logfullpath, $logbuffer, FILE_APPEND | LOCK_EX);
        echo2("Final log stored in: $logfullpath\n");

        function echo2($message){
                global $logbuffer;
                $logbuffer .= $message;
                echo $message;
        }

?>