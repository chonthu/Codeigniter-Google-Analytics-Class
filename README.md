#Codeigniter Google Analytics Api Documentation

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

