var swfu;
var files_queued = 0;
var active_callout_edit;
var callout_desc;
var last_seo_data = false;
var pageTitleDidFocus = false;
var rememberedTemplate;
var rememberedExternal;

$(document).ready(function() {
	
	// Handle the template selection boxes
	$(".box_select").click(function() {
		// Uncheck external link but remember what it was in case they switch back.
		rememberedExternal = $("#external_link").removeClass("active").val();
		$("#external_link").val("");
		
		// Uncheck redirect lower
		$("input[name=redirect_lower]").attr("checked",false).next("div").find("a").removeClass("checked");
		
		$("#template").val($(this).attr("href").substr(1));
		$(".box_select").removeClass("active");
		$(this).addClass("active");
		
		return false;
	});
	
	// If the redirect lower checkbox is checked, remove selected template, otherwise reset it
	$("input[name=redirect_lower]").bind("checked:click",function() {
		if ($(this).attr("checked")) {
			rememberedTemplate = $("#template").val();
			rememberedExternal = $("#external_link").val();
			$(".box_select").removeClass("active");
			$("#template").val("!");
			$("#external_link").removeClass("active").val("");
		} else {
			if (rememberedTemplate == "" && rememberedExternal) {
				$("#external_link").addClass("active").val(rememberedExternal);
				$("#template").val();
			} else if (rememberedTemplate) {
				$("#template").val(rememberedTemplate);
				$(".box_select[href=#" + rememberedTemplate + "]").addClass("active");
			} else {
				$("template").val($(".box_select").addClass("active").attr("href").substr(1));
			}
		}
	});
	
	// Watch for entry into the external link field and switch to a blank template.
	$("input[name=external]").bind("focus",function() {
		// Backup the existing one.
		rememberedTemplate = $("#template").val();
		if (rememberedTemplate && rememberedExternal) {
			$(this).val(rememberedExternal);
		}
		$(".box_select").removeClass("active");
		$("#template").val("");
		if (rememberedTemplate == "!") {
			$("input[name=redirect_lower]").attr("checked",false).next("div").find("a").removeClass("checked");
		}
	}).bind("blur",function() {
		if ($(this).val() == "") {
			$("#template").val(rememberedTemplate);
			if (rememberedTemplate == "!") {
		  		$("input[name=redirect_lower]").attr("checked",true).next("div").find("a").addClass("checked");
			} else {
				$(".box_select[href=#" + rememberedTemplate + "]").addClass("active");
			}
		}
	});
	
	// Walk through each step of page creation.
	$(".next").click(function() {
		nav = $(".form_container nav a");
		
		tab = $(".form_container nav a.active");
		tab.removeClass("active");
		next = tab.next("a").addClass("active");
		
		$("#" + next.attr("href").substr(1)).show();
		$("#" + tab.attr("href").substr(1)).hide();
		
		if (nav.index(tab) == nav.length - 2) {
			$(this).hide();
		}
		
		return false;
	});
	
	// Watch for changes in the template, update the Content tab.
	checkTimer = setInterval(checkTemplate,500);
	
	$(".save_and_preview").click(function() {
		sform = $(this).parents("form");
		sform.attr("target","_blank");
		sform.attr("action","www_root/admin/pages/update/preview/");
		sform.submit();
		
		return false;
	});
	
	// Observe the Nav Title for auto filling the Page Title the first time around.
	$("#nav_title").keyup(function() {
		if (!$("#page_title").get(0).defaultValue && !pageTitleDidFocus) {
			$("#page_title").val($("#nav_title").val());
		}
	});
	$("#page_title").focus(function() { pageTitleDidFocus = true; });
	
	// Callouts
	$("#bigtree_callouts .add_callout").click(function() {
		$.ajax("www_root/admin/ajax/pages/add-callout/", { type: "POST", data: { count: callout_count }, complete: function(response) {
			new BigTreeDialog("Add Callout",response.responseText,function() {
				li = $('<li>');
				li.html('<h4></h4><p>' + $("#callout_type select").get(0).options[$("#callout_type select").get(0).selectedIndex].text + '</p><div class="bottom"><a href="#" class="icon_delete_small"></a></div>');
				
				callout_desc = "";
				skipped_first = false;
				$("#bigtree_dialog_form input, #bigtree_dialog_form textarea, #bigtree_dialog_form select").each(function() {
					if ($(this).attr("type") != "submit") {
						if ($(this).css("display") == "none") {
							tinyMCE.get($(this).attr("id")).save();
							tinyMCE.execCommand('mceRemoveControl',false,$(this).attr("id"));
						}
						if (skipped_first && !callout_desc && $(this).val()) {
							callout_desc = $(this).val();
						}
						skipped_first = true;
						$(this).hide();
						li.append($(this));
					}
				});
				$("#bigtree_callouts ul").append(li);
				$("#bigtree_dialog_overlay, #bigtree_dialog_window").remove();
				
				li.find("h4").html('<span class="icon_sort"></span>' + callout_desc);
				
				callout_count++;
				
				return false;
			},"callout",false,false,true);
		}});
		
		return false;
	});
	
	$("#bigtree_callouts").on("click",".icon_edit_small",function() {
		active_callout_edit = $(this).parents("li");
		
		$.ajax("www_root/admin/ajax/pages/edit-callout/", { type: "POST", data: { count: callout_count, data: active_callout_edit.find(".callout_data").val() }, complete: function(response) {
			new BigTreeDialog("Edit Callout",response.responseText,function() {
				li = $('<li>');
				li.html('<h4></h4><p>' + $("#callout_type select").get(0).options[$("#callout_type select").get(0).selectedIndex].text + '</p><div class="bottom"><a href="#" class="icon_delete_small"></a></div>');
				
				callout_desc = "";
				skipped_first = false;
				$("#bigtree_dialog_form input, #bigtree_dialog_form textarea, #bigtree_dialog_form select").each(function() {
					if ($(this).attr("type") != "submit") {
						if ($(this).css("display") == "none") {
							tinyMCE.get($(this).attr("id")).save();
							tinyMCE.execCommand('mceRemoveControl',false,$(this).attr("id"));
						}
						if (skipped_first && !callout_desc && $(this).val()) {
							callout_desc = $(this).val();
						}
						skipped_first = true;
						$(this).hide();
						li.append($(this));
					}
				});
				active_callout_edit.replaceWith(li);
				$("#bigtree_dialog_overlay, #bigtree_dialog_window").remove();
				
				li.find("h4").html('<span class="icon_sort"></span>' + callout_desc);
				
				callout_count++;
				
				return false;
			},"callout",false,false,true);
		}});
	});
	
	$("#bigtree_callouts").on("click",".icon_delete_small",function() {
		new BigTreeDialog("Delete Callout", '<p class="confirm">Are you sure you want to delete this callout?</p>', $.proxy(function() {
			$(this).parents("li").remove();
		},this),"delete",false,"OK");
		return false;
	});
	
	$("#bigtree_callouts ul").sortable({ items: "li", handle: ".icon_sort" });
});

/*
function updateSEOScore() {
	data = $$("form.module")[0].json_encode(true);
	try {
		data.content = tinyMCE.get("field_resources[page_content]").getContent();
	} catch (er) {
	}
	data.updated_at = page_updated_at;
	if (!last_seo_data) {
		new_data = true;
	} else {
		new_data = false;
		for (i in last_seo_data) {
			if (last_seo_data[i] != data[i]) {
				new_data = true;
			}
		}
	}
	if (new_data) {
		last_seo_data = data;
		new Ajax.Updater("seo_recommendations","www_root/admin/ajax/pages/get-seo-score/", { parameters: data, evalScripts: true });
	}
} */

function checkTemplate() {
	tval = $("input[name=template]");
	if (tval.length) {
		if (template != tval.val()) {
			template = tval.val();
			$("#template_type").load("www_root/admin/ajax/pages/get-template-form/", { page: page, template: template });
		}
	}
}