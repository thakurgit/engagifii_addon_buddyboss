    window.$ = jQuery.noConflict();
	jQuery(document).ready(function($) {
	$('.select-env').change( function() {
			$('.env-loading').show();
			$('.env-loading-msg').text('').removeClass('error success');
			var selectedEnv = jQuery(this).val();
		
		 jQuery.ajax({
        url: 'https://accg.engagifii'+selectedEnv+'.com/assets/environment-config-1.0.json',
        method: 'GET',
        dataType: 'json',
        success: function(result) {
            var apiUrls = {'crmUrl':result.crmBaseUrl,'reportUrl':result.courseReporturl,'authUrl':result.authPolicyDevUrl,'revenueUrl':result.revenueBaseUrl,'doUrl':result.dynamicObjectApprovalUrl,'tnaUrl':result.baseUrl,'eventUrl':result.eventBaseUrl,'legisUrl':result.legislationBaseUrl,'resourceUrl':result.resourceBaseUrl};
			 for (var key in apiUrls) {
				if (apiUrls.hasOwnProperty(key)) {
					if(apiUrls[key].indexOf('api') == -1){
						if(key=='resourceUrl'){
						  apiUrls[key] = apiUrls[key]+'/api/upload';	
						} else {
						  apiUrls[key] = apiUrls[key]+'/api/v1';	
						}
					}
					jQuery('input.'+key).val(apiUrls[key]);
					
				}
			}
			$('.env-loading').hide();
			$('.env-loading-msg').text('API Urls updated!').addClass('success');;
        },
        error: function(xhr, status, error) {
           $('.env-loading').hide();
			$('.env-loading-msg').text('Oops! API Urls update failed!').addClass('error');
        }
    });
	});
     $("#sortable1, #sortable2").sortable({
        connectWith: ".connectedSortable",

        receive: function(event, ui) {
            if (this.id === "sortable1") {
                // Prevent dropping back into source list
                $(ui.sender).sortable('cancel');
                return;
            }

            // When dropped into #sortable2
            if (!ui.item.hasClass("editable")) {
                const label = ui.item.text();
                const id = ui.item.data("id");

                const input = `
                    <input type="text" class="field-label" value="${label}" />
                    <span class="remove-field" style="cursor:pointer; margin-left:10px;">✖</span>
                `;

                ui.item
                    .html(input)
                    .attr("data-original-label", label)
                    .attr("data-id", id)
                    .addClass("editable");
            }
            updateUserFields();
        },

        update: function(event, ui) {
            updateUserFields();
        }
    }).disableSelection();

    // Update hidden field when label is edited
    $(document).on("input", ".field-label", function() {
        updateUserFields();
    });

    // Handle close icon to remove field and restore to source
    $(document).on("click", ".remove-field", function() {
        const $item = $(this).closest("li");
        const originalLabel = $item.attr("data-original-label") || "";
        const id = $item.data("id");

        const restoredItem = $("<li>")
            .addClass("ui-state-default")
            .attr("data-id", id)
            .text(originalLabel);

        $("#sortable1").append(restoredItem);
        $item.remove();

        updateUserFields();
    });

    function updateUserFields() {
        const fields = [];

        $("#sortable2 li").each(function() {
            const id = $(this).data("id");
            const label = $(this).find("input.field-label").val() || "";
            if (id && label) {
                fields.push({ id, label });
            }
        });

        $("#user_fields").val(JSON.stringify(fields));
    }
	});
