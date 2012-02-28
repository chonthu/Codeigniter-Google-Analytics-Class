#Codeigniter Google Analytics Api Documentation

#### Requires sessions

## Getting started

1. Download this class
2. Copy over config file into your codeigniter config folder
3. Autoload sessions and set session key in main config file
3. Copy over the library file into your library folder
4. Navigate to your new ga_api.php config file and change 

	$config['profile_id'] // Your default Google analytics profile id
	(please note you can access this id from looking your Google analytics account under > settings -> profiles -> profile settings)

	$config['email']		= 'example@mail.com'; // GA Account mail
	$config['password']		= 'xxxxxxx'; // GA Account password

5. Once your config is updated, within your controller you can now.

## Example Usage

	$this->load->library('ga_api');

	// Set new profile id if not the default id within your config document
	$this->ga = $this->ga_api->login()->init(array('profile_id' => '182918291281'));

	// Query Google metrics and dimensions
	// Documentation: http://code.google.com/apis/analytics/docs/gdata/dimsmets/dimsmets.html)
	$data = $this->ga
		->dimension('adGroup , campaign , adwordsCampaignId , adwordsAdGroupId')
		->metric('impressions')
		->limit(30)
		->get_object();

	// Also please note, if you using default values you still need to init()
	$data = $this->ga_api->login()
		->dimension('adGroup , campaign , adwordsCampaignId , adwordsAdGroupId')
		->metric('impressions')
		->limit(30)
		->get_object();


	// Please note you can also query against your account and find all the profiles associated with it by
	// grab all profiles within your account
	$data['accounts'] = $this->ga_api->login()->get_accounts();

## Additional Metric Filters

	filter
	and_filter
	or_filter
	when
	sort_by
	limit
	offset
	segment
	dsegment
	and_dsegment
	or_dsegment

## Response types

	get
	get_object
	get_array

## Clear for cache

	clear // cleat init values
	clear_login_data // clear username & password
	clear_cache	 // clear cache folder

