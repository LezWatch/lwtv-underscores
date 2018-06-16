jQuery(document).ready(function($) {

	var alerted = localStorage.getItem('gdpr-alerted') || '';
	if (alerted != 'yes') {
		//$('#GDPRAlert').alert('open');
		$('#GDPRAlert').addClass('show');
	}

	$('#GDPRAlert').on('closed.bs.alert', function () {
		localStorage.setItem('gdpr-alerted','yes');
	})
});
