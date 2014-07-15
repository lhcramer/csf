<?php
  /**
   * Edit Module - Allows for a source module file to be editied.
   *
   * PHP version 5
   *
   * @category Decision_Theater_Software
   * @package  Csf
   * @author   Robert Pahle <robert.pahle@asu.edu>
   * @author   Rahul Salla <rahul.salla@asu.edu>
   * @license  http://github.com/rahulsalla/csf/license.txt ASU Software License
   * @link     http://github.com/rahulsalla/csf
   **/

require_once "Zend/Db.php";
require_once "config.php";
require_once "../".$configuration['phplivex_path'].'/PHPLiveX.php';


$browser = $_SERVER['HTTP_USER_AGENT'];

if (preg_match('/Firefox/i', $browser) == false) {
    echo 'Warning: Using a browser other than Firefox is not supported...';
    echo 'You are currently using '. $browser;
}

/**
 * Writes the contents of the passed in data argument to the filename
 *
 * @param string $data     This content will be written
 * @param string $filename We will be writing data to this file
 *
 * @return none
 * 
 * @access public
 * @static
 * @since Method available since Release 0.1.0
 */
function EditModule_Write_data($data, $pathname, $filename, $sid) {
    $info = 'Error open temp file for writing.';
    $fp = fopen($pathname.$filename.".phpr", 'w');
    if ($fp) {
        if (fwrite($fp, $data)=== false) {
            $info = 'Error writing temp file.';
        } else {
            fclose($fp);
            $info = 'Temp file written.';
            chdir('..');
	    $ex	= '/usr/bin/php -f modules/processors/'.$filename.'.phpr '.$sid.' 2>&1';
	    $point	= popen($ex,'r');
	    $info = '';
	    while(($buffer = fgets($point)) !== false) $info .= $buffer;
	    $err = pclose($point);
	    chdir('includes');
	    if(!unlink($pathname.$filename.".phpr"))
		$info = "File was NOT saved. (Unable to remove temporary file.)\n\n".$info;
	    else {
	        if($err != 0) $info = "Error in this file, will NOT save. Please correct error and save again.\n\n".$info;
		else {
		    $fp = fopen($pathname.$filename, 'w');
		    if ($fp) {
			if (fwrite($fp, $data)=== false) {
			    $info = "Error writing.\n\n".$info;
			} else {
			    fclose($fp);
			    if($info!='') $info = "File was saved, but some messages were returned.\n\n".$info;
			    else $info = "File was saved.\n\n".$info;
    			}
    		    }
		}
	    }
        }
    }
    return($info);
}

$ajax = new PHPLiveX(array("EditModule_Write_data"));
$ajax->Run('../'.$configuration['phplivex_path'].'/phplivex.js');

$db = Zend_Db::factory('pdo_pgsql', $database);
$db->getConnection();

if(!isset($_GET['id'])) die ('Do not call this file directly');

$id	= $_GET['id'];

global $db;

$sql= "select station_variables.sid as sid, "
    . "station_variables.svid as svid, "
    . "station_variables.name as name, "
    . "station_variables.value as value "
    . "from station_variables where sid="
    . $id ." and (station_variables.name='name' or "
    . "station_variables.name='phpfile')";
$result	= $db->fetchAll($sql);

foreach ($result as $station) {
    $value[$station['name']]	= $station['value'];
    $svid[$station['name']]	= $station['svid'];
}

if(isset($_GET['modulename']))
    $value['phpfile']=$_GET['modulename'];

?>

<html>
<head>
<script language="javascript" type="text/javascript">
	function updated(info)
{
    alert(info);
}

function testfunction(data, value)
{
    EditModule_Write_data(value,
               "<?php echo '../modules/processors/'  ?>",
               "<?php echo $value['phpfile'].'.php' ?>",
               "<?php echo $id ?>",
               {'onUpdate': function(response){updated(response);}});
}
</script>

<script language="javascript" type="text/javascript" 
    src="../dist/edit_area/edit_area_full.js"></script>

	<script language="javascript" type="text/javascript">
	editAreaLoader.init({
        id : "textarea_1"			   // textarea id
                ,syntax: "php"		   // syntax to be used for highlighting
                ,start_highlight: true // display with highlight mode on start-up
                ,toolbar: "save, |, undo, redo"
                ,allow_toggle: false
                ,save_callback: "testfunction"
                });
</script>

</head>
<body style="margin: 0; padding: 0; height: 100%; border: none;">
<form method="post">
<textarea id="textarea_1" name="content" cols="80" rows="30" 
    style="width:100%;height:100%;"><?php
if (file_exists('../modules/processors/'.$value['phpfile'].'.php')) {
    $data = file('../modules/processors/'.$value['phpfile'].'.php');
    foreach ($data as $info) {
        echo $info;
    }
} else {
    $data	= file('../modules/template.php');
    foreach ($data as &$info) {
        $info=str_replace('getsetup_module_test','getsetup_module_'.$value['phpfile'],$info);
        echo $info;
    }
    file_put_contents('../modules/processors/'.$value['phpfile'].'.php',$data);
    $sql = 'update station_variables set value=\''.$value['phpfile'].'\' where sid='.$id.' and name=\'phpfile\'';
    $result = $db->fetchAll($sql);
}

?></textarea>
</form> 
</body>
</html>

<?php
echo "<a href='../modules/processors/".$value['phpfile'].".php?id="
.$_GET['id']."' target='_blank'>Run</a><br>";
echo "Name: ".$value['name']." (".$svid['name'].")<br>";
echo "FileName: ".$value['phpfile']." (".$svid['phpfile'].")<br>";
