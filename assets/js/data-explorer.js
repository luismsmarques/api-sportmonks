jQuery(document).ready(function($) {
	var $form = $('#aps-api-form');
	var $endpoint = $('#aps-endpoint');
	var $resourceId = $('#aps-resource-id');
	var $resourceId2 = $('#aps-resource-id2');
	var $resourceId2Row = $('#aps-resource-id2-row');
	var $response = $('#aps-api-response');
	var $responseContent = $('#aps-response-content');
	var $copyButton = $('#aps-copy-response');
	var $bundleSelect = $('#aps-team-bundle-select');
	var $bundleButton = $('#aps-team-bundle-fetch');
	var $bundleResponse = $('#aps-team-bundle-response');
	
	// Show/hide second resource ID field
	$endpoint.on('change', function() {
		if ($(this).val().indexOf('{id2}') !== -1) {
			$resourceId2Row.show();
		} else {
			$resourceId2Row.hide();
		}
	});
	
	// Handle form submission
	$form.on('submit', function(e) {
		e.preventDefault();
		
		var endpoint = $endpoint.val();
		var resourceId = $resourceId.val();
		var resourceId2 = $resourceId2.val();
		var includes = $('#aps-includes').val();
		var filters = $('#aps-filters').val();
		
		if (!endpoint || !resourceId) {
			alert('Por favor, selecione um endpoint e forneça um ID.');
			return;
		}
		
		// Show loading
		$responseContent.text('A carregar...');
		$response.show();
		$copyButton.hide();
		
		$.ajax({
			url: apsDataExplorer.ajaxUrl,
			type: 'POST',
			data: {
				action: 'aps_fetch_api_data',
				nonce: apsDataExplorer.nonce,
				endpoint: endpoint,
				resource_id: resourceId,
				resource_id2: resourceId2,
				includes: includes,
				filters: filters
			},
			success: function(response) {
				if (response.success) {
					$responseContent.text(response.data.json);
					$copyButton.show();
				} else {
					$responseContent.text('Erro: ' + response.data.message);
				}
			},
			error: function() {
				$responseContent.text('Erro ao fazer requisição.');
			}
		});
	});

	function copyText(text) {
		var $textarea = $('<textarea>');
		$textarea.val(text);
		$('body').append($textarea);
		$textarea.select();
		try {
			document.execCommand('copy');
			alert('Resposta copiada para a área de transferência!');
		} catch (err) {
			alert('Erro ao copiar. Por favor, copie manualmente.');
		}
		$textarea.remove();
	}

	if ($bundleButton.length) {
		$bundleButton.on('click', function() {
			var teamId = $bundleSelect.val();
			if (!teamId) {
				return;
			}

			$bundleButton.prop('disabled', true).text('A carregar...');
			$bundleResponse.hide();
			$bundleResponse.find('pre').text('A carregar...');

			$.ajax({
				url: apsDataExplorer.ajaxUrl,
				type: 'POST',
				data: {
					action: 'aps_fetch_team_bundle',
					nonce: apsDataExplorer.nonce,
					team_id: teamId
				},
				success: function(response) {
					if (!response.success) {
						var warn = response.data && response.data.message ? response.data.message : 'Erro ao obter dados.';
						alert(warn);
						return;
					}

					var json = response.data.json || {};
					$bundleResponse.find('[data-section="team"] pre').text(json.team || '');
					$bundleResponse.find('[data-section="fixtures"] pre').text(json.fixtures || '');
					$bundleResponse.find('[data-section="squad"] pre').text(json.squad || '');
					$bundleResponse.find('[data-section="injuries"] pre').text(json.injuries || '');

					$bundleResponse.show();
				},
				error: function() {
					alert('Erro ao fazer requisição.');
				},
				complete: function() {
					$bundleButton.prop('disabled', false).text('Carregar dados completos');
				}
			});
		});
	}

	$(document).on('click', '.aps-copy-section', function() {
		var text = $(this).closest('.aps-bundle-section').find('pre').text();
		if (text) {
			copyText(text);
		}
	});

	$(document).on('click', '.aps-fetch-widget', function() {
		var $card = $(this).closest('.aps-widget-card');
		var action = $(this).data('action');
		var payload = {
			action: action,
			nonce: apsDataExplorer.nonce
		};

		payload.team_id = $card.find('.aps-widget-team-id').val() || '';
		payload.team_id2 = $card.find('.aps-widget-team-id-2').val() || '';
		payload.league_id = $card.find('.aps-widget-league-id').val() || '';
		payload.fixture_id = $card.find('.aps-widget-fixture-id').val() || '';
		payload.player_id = $card.find('.aps-widget-player-id').val() || '';
		payload.start = $card.find('.aps-widget-start').val() || '';
		payload.end = $card.find('.aps-widget-end').val() || '';

		var $output = $card.find('.aps-widget-response');
		$output.text('A carregar...');

		$.ajax({
			url: apsDataExplorer.ajaxUrl,
			type: 'POST',
			data: payload,
			success: function(response) {
				if (response.success) {
					$output.text(response.data.json || '');
				} else {
					$output.text('Erro: ' + (response.data.message || 'Erro ao obter dados.'));
				}
			},
			error: function() {
				$output.text('Erro ao fazer requisição.');
			}
		});
	});
	
	// Copy response
	$copyButton.on('click', function() {
		var text = $responseContent.text();
		copyText(text);
	});
});

