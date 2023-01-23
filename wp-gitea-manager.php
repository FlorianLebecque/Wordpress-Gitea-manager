<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-rbsA2VBKQhggwzxH7pPCaAqO46MgnOM80zW1RWuH61DGLwZJEdK2Kadq2F9CUG65" crossorigin="anonymous">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-kenU1KFdBIe4zVF0s0G1M5b4hcpxyD9F7jL+jjXkk+Q2h455rYXK/7HAuoJl+0I4" crossorigin="anonymous"></script>
    <title>Wordpress Gitea Manager</title>
</head>
<?php
    include("wp-config.php");
    define("GPM_CONFIG_FILE","wgm_config.txt");
    define("GIT_COMMIT_LIMIT",150);
    
    //make sure that all required data is added
    if((!defined( 'GIT_TARGET' ))&&(!defined( 'GIT_HOST' ))&&(!defined( 'GIT_USER' ))&&(!defined( 'GIT_REPO' ))&&(!defined( 'GIT_PASS' ))){
        DisplayError("<pre>[GIT_HOST,GIT_USER,GIT_REPO,GIT_PASS,GIT_TARGET] are not defined in wp-config.php \n please add : \n \n define( 'GIT_HOST', 'YOUR_HOST' ); \n define( 'GIT_USER', 'GIT_USER' ); \n define( 'GIT_REPO', 'YOUR_REPO' ); \n define( 'GIT_PASS', 'PASSWORD' ); \n define( 'GIT_TARGET', 'YOUR_FOLRDER_TARGER' );</pre>");
        die();
    }

    global $urls;
    $urls = [
        "branch"    => GIT_HOST.'/api/v1/repos/'.GIT_USER.'/'.GIT_REPO.'/branches',
        "commits"   => GIT_HOST.'/api/v1/repos/'.GIT_USER.'/'.GIT_REPO.'/commits',
        "git"   => GIT_HOST.'/api/v1/repos/'.GIT_USER.'/'.GIT_REPO.'/git/commits/',
        "file"  =>  GIT_HOST.'/api/v1/repos/'.GIT_USER.'/'.GIT_REPO.'/raw',
        "download"  => GIT_HOST.'/'.GIT_USER.'/'.GIT_REPO.'/archive'
    ];

    //add config file to store the last registered commit
    if(!file_exists(GPM_CONFIG_FILE)){
        $firsttime = true;
        if(!createFile(GPM_CONFIG_FILE)){
            DisplayError("Could not create the config file : ".GPM_CONFIG_FILE);
        }
    }
    
    //load all the branche of the repo
    $branches = CallAPI("GET",$urls["branch"]);
    if(gettype($branches)!=gettype([])){
        DisplayError("Branch resquest error : ".$branches);
        die();
    }
    if(count($branches) == 0){
        DisplayError("No branch found, you might need to check the configuration [wp-config.php]");
    }
    $selected_branch = $branches[0]["name"];
    if(isset($_GET["branch"])){
        $target = htmlspecialchars($_GET["branch"]);
        foreach($branches as $branch){
            if($branch["name"] == $target){
                $selected_branch = $branch["name"];
                break;
            }
        }
    }   

    //load the last commit
    $commits = CallAPI("GET",$urls["commits"]."?sha=$selected_branch&stat=false");
    if(gettype($commits)!=gettype([])){
        DisplayError("Commits URL : ".$urls["commits"]."?sha=$selected_branch&stat=false");
        DisplayError("Commits resquest error : ".$commits);
        die();
    }
    if(count($commits) == 0){
        DisplayError("No commits found, you might need to check the configuration [wp-config.php]");
    }

    //select an other commit if user specified
    global $last_commit;
    $last_commit = $commits[0];
    if(isset($_POST["commit"])){
        $selected_commit = htmlspecialchars($_POST["commit"]);

        foreach($commits as $commit){
            if($commit["sha"] == $selected_commit){
                $last_commit = $commit;
            }
        }
    }

    //check if we are at the same commit
   
    $last_registered_commit_id = configreadFile(GPM_CONFIG_FILE);
    $modif = [];
    $commits_modif = [];
    $files_modif = [];
    if($last_registered_commit_id != ""){   //we don't have any commit, we must download all the repo
        global $last_registered_commit;
        $last_registered_commit = CallAPI("GET",$urls["git"].$last_registered_commit_id);
        //we are behind
        if(($last_commit["sha"] != $last_registered_commit_id)&&(strtotime($last_commit["created"])>strtotime($last_registered_commit["created"]))){

            $modif = findPath($last_commit,$last_registered_commit_id); //get a list of all the commit between us and the target
            
            //identify the commit and the file that need to be change
            for($i = 0; $i < count($modif)-1 ; $i ++ ){
                $commit = CallAPI("GET",$urls["git"].$modif[$i]);
                array_push($commits_modif,$commit);

                foreach($commit["files"] as $file_obj){
                    if(!isset($files_modif[$file_obj["filename"]])){
                        $files_modif[$file_obj["filename"]] = $commit["sha"];
                    }
                }

            }

        }
    }else{
        $last_registered_commit["created"] = "none";
        $last_registered_commit["sha"] = "none";
        $last_registered_commit["commit"]["message"] = "none";
    }


    //check if user request a pull to load the last commit
    if(isset($_POST["pass"])&&(isset($_POST["pull"]))){
        if($_POST["pass"] == GIT_PASS){
            SaveFile($files_modif,GIT_TARGET);
        }else{
            DisplayError("An error has occured");
        }
    }

    //check if user request a download to load the last commit
    if(isset($_POST["pass"])&&(isset($_POST["download"]))){
        if($_POST["pass"] == GIT_PASS){
            recurseRmdir(GIT_TARGET);
            mkdir(GIT_TARGET, 0777, true);
            if(Download($urls["download"]."/$selected_branch.zip")){
                writeIdToFile($last_commit["sha"],GPM_CONFIG_FILE);
                DisplaySuccess("Repository succesfully downloaded");
            }
        }else{
            DisplayError("An error has occured");
        }
    }

?>

<body>
    <div class="container bg-light mt-5 mb-5 p-1 rounded">
        <div class="row vstack gap-3 p-2">
            <div>
                <h1>Wordpress Gitea Manager</h1>
            </div>
            <div>
                <h2>Setup</h2>
                <ul class="list-group">
                    <li class="list-group-item"><?= GIT_HOST ?></li>
                    <li class="list-group-item"><?= GIT_USER ?></li>
                    <li class="list-group-item"><?= GIT_REPO ?></li>
                    <li class="list-group-item"><?= GIT_TARGET ?></li>
                </ul>
            </div>
            <div>
                <h2>Download</h2>
                <form action="" method="post" onsubmit="return confirm('Are you sure you want to submit?');">
                    <div class="input-group mb-3">
                        <input name="download" type="text" hidden value="download" id="download">
                        <div class="form-floating">
                            <input name="pass" type="password" class="form-control" id="pass" placeholder="password">
                            <label for="pass">Password</label>
                        </div>
                        <input type="submit" value="Download" class="btn btn-primary btn-lg">
                    </div>
                </form>
                <p>Warning, downloading will clear every files that don't belong to the repository</p>
            </div>
            <div>
                <h2>Pull</h2>
                <form action="" method="post" onsubmit="return confirm('Are you sure you want to submit?');">
                    <div class="input-group mb-3">
                        <input name="commit" type="text" hidden value="<?= $last_commit["sha"] ?>" >
                        <input name="pull"   type="text" hidden value="pull" id="pull">
                        <div class="form-floating">
                            <input name="pass" type="password" class="form-control" id="pass" placeholder="password">
                            <label for="pass">Password</label>
                        </div>
                        <input type="submit" value="Pull" class="btn btn-primary btn-lg">
                    </div>
                </form>
                <p>Will update to the latest commit</p>

            </div>
            
            <div>
                <h2>Branches/Commits</h2>
                <?php if(count($modif) > 0){ ?>
                    <div class="mb-3">
                        <h5>New commits</h5>
                        <table class="table">
                            <thead>
                                <tr>
                                    <th scope="col">Date</th>
                                    <th scope="col">Message</th>
                                    <th scope="col">ID</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($commits_modif as $commit){ ?>
                                    <tr>
                                        <td class="text-success"><?= date("Y-m-d H:i", strtotime($commit["created"]))?></td>
                                        <td class="text-success"><?= $commit["commit"]["message"]?></td>
                                        <td class="text-success"><?= $commit["sha"] ?></td>
                                    </tr>
                                <?php } ?>
                            </tbody>
                        </table>
                        <h5>Files</h5>
                        <ul class="list-group">
                            <?php foreach($files_modif as $file => $commit_id){ ?>
                                <li class="list-group-item hstack gap-3"><div><?= $file ?></div><div class="text-muted"><?= substr($commit_id, 0, 10) ?></div></li>
                            <?php }?>
                        </ul>
                    </div>
                <?php } ?>
                <form action="" method="get">
                    <div class="input-group mb-3">
                        <label class="input-group-text" for="branch">Branch</label>
                        <select id="branch" name="branch" class="form-select">
                            <?php
                                foreach($branches as $branch){
                                    if($branch["name"] == $selected_branch){
                                        ?>
                                            <option selected value="<?= $branch["name"] ?>"><?= $branch["name"] ?></option>
                                        <?php
                                    }else{
                                        ?>
                                            <option value="<?= $branch["name"] ?>"><?= $branch["name"] ?></option>
                                        <?php
                                    }
                                }
                            ?>
                        </select>
                        <input type="submit" value="Switch" class="btn btn-primary">
                    </div>
                </form>
                <div>
                    <table class="table">
                        <thead>
                            <tr>
                                <th scope="col">Commit</th>
                                <th scope="col">Date</th>
                                <th scope="col">Message</th>
                                <th scope="col">Id</th>
                                <th scope="col"></th>
                                <th scope="col">Revert</th>
                            </tr>
                        </thead>
                        <tbody>
                            
                            <?php
                                $count = 0;
                            ?>
                            <?php foreach($commits as $commit){ 
                                $commit_url = GIT_HOST."/".GIT_USER."/".GIT_REPO."/commit/".$commit['sha'];
                                        if($commit["sha"] == $last_registered_commit_id){ ?>

                                    <tr class="table-primary">
                                        <td>Current</td>
                                        <td><?= date("Y-m-d H:i", strtotime($commit["created"]))?></td>
                                        <td><?= $commit["commit"]["message"]?></td>
                                        <td><a href="<?= $commit_url ?>"><?= substr($commit["sha"], 0, 10);?></a></td>
                                        <td></td>
                                        <td></td>
                                    </tr>

                                <?php   }else{ ?>

                                
                                    <tr>
                                        <td><?= ($count==0)? "Last commit":"" ?></td>
                                        <td><?= date("Y-m-d H:i", strtotime($commit["created"]))?></td>
                                        <td><?= $commit["commit"]["message"]?></td>
                                        <td><a href="<?= $commit_url ?>"><?= substr($commit["sha"], 0, 10);?></a></td>
                                        <form action="" method="post" onsubmit="return confirm('Are you sure you want to submit?');">
                                            <td><input type="password" class="form-control" placeholder="Password" name="pass" ></td>
                                            <td> 
                                                <input name="commit" type="text" hidden value="<?= $commit["sha"] ?>" >
                                                <input type="hidden" name ="<?= ($count==0 || strtotime($commit["created"])>strtotime($last_registered_commit["created"]) )? "pull":"revert" ?>">
                                                <input type="submit" value="<?= ($count==0 || strtotime($commit["created"])>strtotime($last_registered_commit["created"]) )? "Pull":"Revert" ?>" class="btn <?= ($count==0 || strtotime($commit["created"])>strtotime($last_registered_commit["created"]) )? "btn-outline-success":"btn-outline-danger" ?>" />
                                            </td>
                                        </form>
                                    </tr>

                                <?php   }
                                    $count++;
                                }
                            ?>
                            
                        </tbody>
                    </table>
                </div>

                
            </div>
            
        </div>
    </div>
</body>
</html>

<?php
    function Download($url){
        $remote = $url;
        $local_folder = GIT_TARGET;
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $remote);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $data = curl_exec ($ch);
        curl_close ($ch);
        
        mkdir("tmp", 0777, true);

        $file = fopen('tmp/file.zip', "w");
        fputs($file, $data);
        fclose($file);
        
        

        $zip = new ZipArchive;
        $res = $zip->open('tmp/file.zip');
        if ($res === TRUE) {
            $zip->extractTo('tmp');
            $zip->close();

            Move_Folder_To("tmp/".GIT_REPO,GIT_TARGET);

            unlink('tmp/file.zip');
            rmdir("tmp");

            return true;

        } else {

            
            unlink('tmp/file.zip');
            rmdir("tmp");
            DisplayError("You might need to check the configuration");

            return false;
        }

    
    }

    function Move_Folder_To($source, $target){
        if( !is_dir($target) ){
            mkdir(dirname($target),0755,true);
        }
        rename( $source,  $target);
    }

    function recurseRmdir($dir) {
        $files = array_diff(scandir($dir), array('.','..'));
        foreach ($files as $file) {
        (is_dir("$dir/$file") && !is_link("$dir/$file")) ? recurseRmdir("$dir/$file") : unlink("$dir/$file");
        }
        return rmdir($dir);
    }

    function CallAPI($method, $url) {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $method);
        $response = curl_exec($curl);
        $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
        if ($status != 200) {
            return $status;
        } else {
            return json_decode($response, true);
        }
    }
    
    function CallFile($file,$commit) {
        global $urls;
        $url = $urls["file"]."/$file?ref=$commit";

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "GET");
        $response = curl_exec($curl);
        $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
        if ($status != 200) {
            return $status;
        } else {
            return $response;
        }
    }

    function writeIdToFile($id, $filepath) {
        $file = fopen($filepath, "w");
        fwrite($file, $id);
        fclose($file);
        return true;
    }
    
    function DisplayError($msg){
        ?>

        <div class="container mt-5 alert alert-danger" role="alert">
            <?= $msg ?>
        </div>

        <?php
    }

    function DisplaySuccess($msg){
        ?>

        <div class="container mt-5 alert alert-success" role="alert">
            <?= $msg ?>
        </div>

        <?php
    }

    function createFile($filepath) {
        $dir = dirname($filepath);
        if (!file_exists($dir)) {
            mkdir($dir, 0777, true);
        }
        if (!file_exists($filepath)) {
            $file = fopen($filepath, "w");
            fclose($file);
            return true;
        }
        return false;
    }

    function configreadFile($filepath) {
        if (file_exists($filepath)) {
            return file_get_contents($filepath);
        } else {
            return false;
        }
    }

    function findPath($childNode, $parentNodeValue) {
        global $urls;
        $queue = new SplQueue();
        $visited = [];
        $previous = [];
        $path = [];
    
        $queue->enqueue($childNode);
        //$visited[$childNode["sha"]] = true;
    
        $count = 0;

        while((!$queue->isEmpty())&&($count < GIT_COMMIT_LIMIT)) {
            $node = $queue->dequeue();
            if ($node["sha"] === $parentNodeValue) {
                break;
            }

            foreach ($node["parents"] as $parent) {
                if (!isset($visited[$parent["sha"]])) {

                    $visited[$parent["sha"]] = true;
                    $previous[$parent["sha"]] = $node;

                    $parent_commit = CallAPI("GET",$urls["git"].$parent["sha"]);


                    $queue->enqueue($parent_commit);
                }
            }

            $count ++;
        }
    
        if (!isset($previous[$parentNodeValue])) {
            return false;
        } else {
            $currentNode = $parentNodeValue;
            while ($currentNode !== $childNode["sha"]) {
                array_unshift($path, $currentNode);
                $currentNode = $previous[$currentNode]["sha"];
            }
            array_unshift($path, $childNode["sha"]);

            return $path;
        }
    }

    function SaveFile($files_modif,$folder_target){
        //http://localhost:3000/api/v1/repos/florian/iziii-printer/raw/dqsd?ref=1aef94f491d0c26d1fde536376143f1d776f1ae8
        global $last_commit;

        foreach($files_modif as $file => $commit_id){

            $commit_id = $last_commit["sha"];

            if (!file_exists($folder_target)) {
                mkdir($folder_target, 0777, true);
            }

            $content = CallFile($file,$commit_id);

            if($content == 404){
                if (file_exists($folder_target."/".$file)) {
                    return unlink($folder_target."/".$file);
                }
            }else if(gettype($content) != gettype(0)){

                $dir = dirname($folder_target."/".$file);
                if (!file_exists($dir)) {
                    mkdir($dir, 0777, true);
                }
                
                $file = fopen($folder_target."/".$file, "w");
                fwrite($file, $content);
                fclose($file);
                
            }else{
                DisplayError("An error has occured, status: $content \n Filename: $file \n Commit:$commit_id");
            }


        }
        
        writeIdToFile($last_commit["sha"],GPM_CONFIG_FILE);

        DisplaySuccess("All files have been updated");
    }