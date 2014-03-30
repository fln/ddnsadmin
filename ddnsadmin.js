function show_error(str) {
	$('#errors').append('<div class="alert alert-danger alert-dismissable"><button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button><strong>Error!</strong> ' + str + '</div>');
}


function get_auth_data(form_element) {
	var data = {};
	$.each(form_element, function(key, val) {
		if (val.name) {
			data[val.name] = val.value;
		}
	});

	return data;
}

function attach_button_action() {
	$('#records [data-name="delete-button"] button').on('click', function (e) {
		var record = {};
		e.preventDefault();
		var data = get_auth_data(document.getElementById('auth_form'));

		$(this).closest('tr').find('[data-var="yes"]').each(function () {
			record[$(this).attr('data-name')] = $(this).text();
		});

		data['record'] = record;
		$.post($('#proxy-path').val()+'/delete-record', JSON.stringify(data), reload_zone);
	});
}

function reload_zone() {
	var auth_data = get_auth_data(document.getElementById('auth_form'));

	$("#records > tbody > tr").remove();

	$.post($('#proxy-path').val()+'/axfr', JSON.stringify(auth_data), function (data) {
		var body;
		var rr_filter = $('#rr-filter').val().split(',');

		if (data.error) {
			show_error(data.message);
			return;
		}

		body = $('#records > tbody');
		$.each(data.records, function (key, val) {
			var r = new Array();
			var i = 0;
			
			if ($.inArray(val['type'], rr_filter) != -1)
				return;

			r[i++] = '<tr>';
			$.each(val, function (rkey, rval) {
				if (rkey == 'name') {
					if (rval.slice(-1*(auth_data.zone.length)) == auth_data.zone) {
						rval = rval.slice(0,-1*(auth_data.zone.length+1));
					}
					r[i++] = '<td data-name="' + rkey + '" data-var="yes">' + rval + '<span class="text-muted">' + (rval.length == 0 ? '' : '.') + auth_data.zone + '</span>' + '</td>';
				} else {
					r[i++] = '<td data-name="' + rkey + '" data-var="yes">' + rval + '</td>';
				}
			});
			r[i++] = '<td data-name="delete-button"><button type="button" class="btn btn-danger btn-xs"><span class="glyphicon glyphicon-minus"></span></button></td>';
			r[i++] = '</tr>';
			body.append(r.join(''));
		});
		attach_button_action();
		
	});

}

function connect_to_proxy() {

$('#rr-add [name="type"]').empty();
$('#key-type').empty();

$.post($('#proxy-path').val()+'/ping', '', function (data) {

	$('#proxy-path').closest("div.form-group").removeClass('has-error');
	$('#proxy-path').closest("div.form-group").addClass('has-success');

	$.post($('#proxy-path').val()+'/supported-key-types', '', function (data) {
		var options = new Array();
		Object.keys(data).forEach(function (key) {
			options.push('<option value="' + key + '">' + data[key] + '</option>');
		});
		$('#key-type').append(options.join(''));
	});

	$.post($('#proxy-path').val()+'/supported-rr-types', '', function (data) {
		var options = new Array();
		Object.keys(data).forEach(function (key) {
			options.push('<option value="' + key + '">' + data[key] + '</option>');
		});
		$('#rr-add [name="type"]').append(options.join(''));
	});
}).fail(function() {
	$('#proxy-path').closest("div.form-group").addClass('has-error');
	$('#proxy-path').closest("div.form-group").removeClass('has-success');
});
}

$(function () {
	$(document).ajaxError(function(event, jqxhr, settings, exception) {
		if (jqxhr.responseText.length > 0) {
			show_error(jqxhr.responseText);
		} else {
			show_error(jqxhr.status + " " + jqxhr.statusText);
		}
	});

	connect_to_proxy();

	$('#proxy-path').on('change', connect_to_proxy);

	$('#zone').on('change', function (e) {
		var data = {};
		data[this.name] = this.value;

		$.post($('#proxy-path').val()+'/zone-to-server', JSON.stringify(data), function (data) {
			$('#server').val(data.server);
		});
	});

	$('#auth_form').on('submit', function (e) {
		e.preventDefault();
		reload_zone();
	});

	$('#records [data-name="add-button"] button').on('click', function (e) {
		e.preventDefault();
		var data = get_auth_data(document.getElementById('auth_form'));
	        var record = {};
		$.each(document.getElementById('rr-add'), function(key, val) {
			if (val.name) {
				if (val.name == 'name') {
					record[val.name] = val.value + (val.value.length == 0 ? '' : '.') + data.zone;
				} else {
					record[val.name] = val.value;
				}
			}
		});

		data['record'] = record;
		$.post($('#proxy-path').val()+'/add-record', JSON.stringify(data), function (data) {
			if (data.error) {
                        	show_error(data.message);
	                } else {
				reload_zone();
			}
			//alert(JSON.stringify(data));
                });
	});
});
