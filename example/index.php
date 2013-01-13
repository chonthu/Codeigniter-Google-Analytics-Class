<?

function autoload($className)
{
    $className = ltrim($className, '\\');
    $fileName  = '';
    $namespace = '';
    if ($lastNsPos = strrpos($className, '\\')) {
        $namespace = substr($className, 0, $lastNsPos);
        $className = substr($className, $lastNsPos + 1);
        $fileName  = str_replace('\\', DIRECTORY_SEPARATOR, $namespace) . DIRECTORY_SEPARATOR;
    }

    $fileName .= str_replace('_', DIRECTORY_SEPARATOR, $className) . '.php';

    require __DIR__ . '/../' . $fileName;
}

spl_autoload_register('autoload');

$config['profile_id']	= '55334904'; // GA profile id
$config['email']		= 'chonthu@gmail.com'; // GA Account mail
$config['password']		= 'cxm3dk411'; // GA Account password

$config['cache_data']	= false; // request will be cached
$config['cache_folder']	= '/cache'; // read/write
$config['clear_cache']	= array('date', '1 day ago'); // keep files 1 day
	
$config['debug']		= true; // print request url if true

$ga = new \Google\Analytics($config);

$data = $ga
    ->dimension('adGroup , campaign , adwordsCampaignId , adwordsAdGroupId')
    ->metric('impressions')
    ->limit(30)
    ->get_object();

print_r($data);