jQuery(document).ready(function($) {
	var teamIndex = $('.aps-team-item').length;
	var $searchButton = $('#aps-team-search-button');
	var $searchInput = $('#aps-team-search');
	var $searchResults = $('#aps-team-search-results');
	
	// Add team
	$('#aps-add-team').on('click', function() {
		$.ajax({
			url: apsSettings.ajaxUrl,
			type: 'POST',
			data: {
				action: 'aps_add_team',
				nonce: apsSettings.nonce
			},
			success: function(response) {
				if (response.success) {
					var template = $('#aps-team-template').html();
					template = template.replace(/\{\{index\}\}/g, response.data.index);
					$('#aps-teams-list').append(template);
					teamIndex++;
				}
			}
		});
	});

	function renderSearchResults(teams) {
		if (!teams.length) {
			$searchResults.html('<p>' + apsSettings.i18n.noResults + '</p>');
			return;
		}

		var html = '<ul class="aps-team-results">';
		teams.forEach(function(team) {
			html += '<li data-team-id="' + team.id + '" data-team-name="' + team.name + '">';
			if (team.image_path) {
				html += '<img src="' + team.image_path + '" alt="" class="aps-team-result-logo" />';
			}
			html += '<span class="aps-team-result-name">' + team.name + '</span>';
			html += '<button type="button" class="button aps-add-team-from-search">' + apsSettings.i18n.addTeam + '</button>';
			html += '</li>';
		});
		html += '</ul>';
		$searchResults.html(html);
	}

	function addTeamWithData(teamId, teamName) {
		$.ajax({
			url: apsSettings.ajaxUrl,
			type: 'POST',
			data: {
				action: 'aps_add_team',
				nonce: apsSettings.nonce
			},
			success: function(response) {
				if (response.success) {
					var template = $('#aps-team-template').html();
					template = template.replace(/\{\{index\}\}/g, response.data.index);
					var $item = $(template);
					$item.find('input[name*="[team_id]"]').val(teamId);
					$item.find('input[name*="[team_name]"]').val(teamName);
					$('#aps-teams-list').append($item);
					teamIndex++;
				}
			}
		});
	}

	$searchButton.on('click', function() {
		var query = $searchInput.val().trim();
		if (!query) {
			return;
		}

		$searchButton.prop('disabled', true).text(apsSettings.i18n.searching);
		$searchResults.empty();

		$.ajax({
			url: apsSettings.ajaxUrl,
			type: 'POST',
			data: {
				action: 'aps_search_team',
				nonce: apsSettings.nonce,
				query: query
			},
			success: function(response) {
				if (response.success) {
					renderSearchResults(response.data.teams || []);
				} else {
					$searchResults.html('<p>' + apsSettings.i18n.error + ' ' + response.data.message + '</p>');
				}
			},
			error: function() {
				$searchResults.html('<p>' + apsSettings.i18n.error + ' ' + apsSettings.i18n.requestFailed + '</p>');
			},
			complete: function() {
				$searchButton.prop('disabled', false).text(apsSettings.i18n.search);
			}
		});
	});

	$(document).on('click', '.aps-add-team-from-search', function() {
		var $item = $(this).closest('li');
		var teamId = $item.data('team-id');
		var teamName = $item.data('team-name');
		addTeamWithData(teamId, teamName);
	});
	
	// Remove team
	$(document).on('click', '.aps-remove-team', function() {
		if (!confirm(apsSettings.i18n.confirmRemove)) {
			return;
		}
		
		var $item = $(this).closest('.aps-team-item');
		var index = $item.data('index');
		
		$.ajax({
			url: apsSettings.ajaxUrl,
			type: 'POST',
			data: {
				action: 'aps_remove_team',
				nonce: apsSettings.nonce,
				index: index
			},
			success: function(response) {
				if (response.success) {
					$item.remove();
				}
			}
		});
	});
	
	// Test API token
	$('#aps-test-token').on('click', function() {
		var $button = $(this);
		var token = $('#aps_smonks_api_token').val();
		
		if (!token) {
			alert(apsSettings.i18n.error + ' ' + apsSettings.i18n.tokenRequired);
			return;
		}
		
		$button.prop('disabled', true).text(apsSettings.i18n.testing);
		
		$.ajax({
			url: apsSettings.ajaxUrl,
			type: 'POST',
			data: {
				action: 'aps_test_api_token',
				nonce: apsSettings.nonce,
				token: token
			},
			success: function(response) {
				if (response.success) {
					alert(apsSettings.i18n.success + ' ' + response.data.message);
				} else {
					alert(apsSettings.i18n.error + ' ' + response.data.message);
				}
			},
			error: function() {
				alert(apsSettings.i18n.error + ' ' + apsSettings.i18n.requestFailed);
			},
			complete: function() {
				$button.prop('disabled', false).text(apsSettings.i18n.testToken);
			}
		});
	});
	
	// Manual sync
	$('#aps-manual-sync').on('click', function() {
		var $button = $(this);
		var $status = $('#aps-sync-status');
		
		$button.prop('disabled', true);
		$status.html('A sincronizar...');
		
		$.ajax({
			url: apsSettings.ajaxUrl,
			type: 'POST',
			data: {
				action: 'aps_manual_sync',
				nonce: apsSettings.nonce
			},
			success: function(response) {
				if (response.success) {
					$status.html('<span style="color: green;">' + response.data.message + '</span>');
				} else {
					$status.html('<span style="color: red;">Erro: ' + (response.data.message || 'Erro desconhecido') + '</span>');
				}
			},
			error: function() {
				$status.html('<span style="color: red;">Erro ao sincronizar.</span>');
			},
			complete: function() {
				$button.prop('disabled', false);
				setTimeout(function() {
					$status.html('');
				}, 5000);
			}
		});
	});
});

