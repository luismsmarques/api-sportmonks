(function ($) {
  function renderFields(componentId) {
    var registry = apsComponents.registry || {};
    var component = registry[componentId];
    var $fields = $("#aps-component-fields");
    var $description = $("#aps-component-description");

    $fields.empty();
    $description.empty();

    if (!component) {
      return;
    }

    if (component.description) {
      $description.text(component.description);
    }

    (component.fields || []).forEach(function (field) {
      var $row = $("<div />", { class: "aps-component-field" });
      var $label = $("<label />").text(field.label || field.key);
      var $input;

      if (field.type === "select") {
        $input = $("<select />", { "data-key": field.key });
        $.each(field.options || {}, function (value, label) {
          var $opt = $("<option />", { value: value }).text(label);
          $input.append($opt);
        });
      } else {
        $input = $("<input />", {
          type: field.type || "text",
          "data-key": field.key,
        });
      }

      if (field.default !== undefined && field.default !== null && field.default !== "") {
        $input.val(field.default);
      }

      $row.append($label).append($input);
      $fields.append($row);
    });
  }

  function collectArgs() {
    var args = {};
    $("#aps-component-fields [data-key]").each(function () {
      var key = $(this).data("key");
      var value = $(this).val();
      if (value !== "" && value !== null && value !== undefined) {
        args[key] = value;
      }
    });
    return args;
  }

  $(function () {
    $("#aps-component-select").on("change", function () {
      renderFields($(this).val());
    });

    $("#aps-component-preview").on("click", function () {
      var componentId = $("#aps-component-select").val();
      if (!componentId) {
        alert(apsComponents.messages.noComponent);
        return;
      }

      var $preview = $("#aps-component-preview-html");
      var $json = $("#aps-component-preview-json");
      $preview.html('<p class="aps-loading">' + apsComponents.messages.loading + "</p>");
      $json.text("");

      $.post(apsComponents.ajaxUrl, {
        action: "aps_preview_component",
        nonce: apsComponents.nonce,
        component_id: componentId,
        args: collectArgs(),
      })
        .done(function (response) {
          if (response && response.success) {
            $preview.html(response.data.html || "");
            $json.text(response.data.json || "");
            if (typeof window.APSComponentsFrontInit === "function") {
              window.APSComponentsFrontInit();
            }
          } else {
            $preview.html("<p>" + (response.data && response.data.message ? response.data.message : "Erro") + "</p>");
          }
        })
        .fail(function () {
          $preview.html("<p>Erro</p>");
        });
    });
  });
})(jQuery);
