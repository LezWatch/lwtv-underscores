// jQuery dismissable - sets localStorage a year

jQuery(document).ready(function($) {
	var gdpr = localStorage.getItem('gdpr-alerted') || '';
	var year = new Date().setFullYear(new Date().getFullYear() + 1);

	if (gdpr < new Date()) {
		$('#GDPRAlert').addClass('show');
		localStorage.removeItem('gdpr-alerted');
	}

	$('#GDPRAlert').on('closed.bs.alert', function () {
		localStorage.setItem('gdpr-alerted',year);
	})
});
