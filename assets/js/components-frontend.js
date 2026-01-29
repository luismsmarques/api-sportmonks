(function ($) {
  function fetchStandings($component, seasonId) {
    var leagueId = $component.data("league-id") || "";
    var fixtureId = $component.data("fixture-id") || "";

    $component.addClass("is-loading");

    $.post(apsComponentsFront.ajaxUrl, {
      action: "aps_fetch_standings",
      nonce: apsComponentsFront.nonce,
      season_id: seasonId,
      league_id: leagueId,
      fixture_id: fixtureId,
    })
      .done(function (response) {
        if (response && response.success) {
          $component.find(".aps-standings-table-wrapper").html(response.data.html || "");
        } else {
          $component.find(".aps-standings-table-wrapper").html("<p class=\"aps-standings-empty\">" + apsComponentsFront.labels.error + "</p>");
        }
      })
      .fail(function () {
        $component.find(".aps-standings-table-wrapper").html("<p class=\"aps-standings-empty\">" + apsComponentsFront.labels.error + "</p>");
      })
      .always(function () {
        $component.removeClass("is-loading");
      });
  }

  function applyPaginationForList($component, $list) {
    if (!$list.length) return;
    var pageSize = parseInt($list.attr("data-page-size") || "5", 10);
    var $items = $list.find(".aps-schedule-item-premium");
    var $visible = $items.filter(function () {
      return !$(this).hasClass("aps-schedule-item--filtered");
    });
    var totalVisible = $visible.length;
    $items.addClass("aps-schedule-item--hidden");
    $visible.slice(0, pageSize).removeClass("aps-schedule-item--hidden");
    var $pagination = $component.find(".aps-schedule-pagination[data-tab=\"" + $list.attr("data-tab") + "\"]");
    if ($pagination.length) {
      $pagination.toggle(totalVisible > pageSize);
    }
  }

  function applyScheduleFilters($component) {
    var league = ($component.find(".aps-schedule-filter-league").val() || "").toString();
    var location = ($component.find(".aps-schedule-filter-location").val() || "").toString();
    var visibleCount = 0;

    $component.find(".aps-schedule-item-premium").each(function () {
      var $item = $(this);
      var itemLeague = ($item.attr("data-league") || "").toString();
      var itemLocation = ($item.attr("data-location") || "").toString();
      var matchesLeague = !league || itemLeague === league;
      var matchesLocation = !location || itemLocation === location;
      var isVisible = matchesLeague && matchesLocation;
      if (isVisible) {
        visibleCount += 1;
        $item.removeClass("aps-schedule-item--filtered");
      } else {
        $item.addClass("aps-schedule-item--filtered");
      }
    });

    $component.find(".aps-schedule-list-premium.aps-schedule-tab-list").each(function () {
      applyPaginationForList($component, $(this));
    });

    $component.find(".aps-schedule-count").text(visibleCount);
    $component.find(".aps-schedule-no-results").toggle(visibleCount === 0);
  }

  $(document).on("change", ".aps-standings-season-select", function () {
    var $component = $(this).closest(".aps-competition-standings");
    var seasonId = $(this).val();
    if (!$component.length || !seasonId) {
      return;
    }
    fetchStandings($component, seasonId);
  });

  $(document).on("change", ".aps-schedule-filter select", function () {
    var $component = $(this).closest(".aps-team-schedule-results");
    if (!$component.length) {
      return;
    }
    applyScheduleFilters($component);
  });

  $(document).on("click", ".aps-schedule-tab", function () {
    var $tab = $(this);
    var $component = $tab.closest(".aps-team-schedule-results");
    var tabKey = $tab.attr("data-tab");
    if (!$component.length || !tabKey) return;
    $component.find(".aps-schedule-tab").attr("aria-selected", "false").removeClass("is-active");
    $tab.attr("aria-selected", "true").addClass("is-active");
    $component.find(".aps-schedule-tab-panel").attr("aria-hidden", "true");
    $component.find(".aps-schedule-tab-panel[data-tab=\"" + tabKey + "\"]").attr("aria-hidden", "false");
  });

  $(document).on("click", ".aps-schedule-load-more", function () {
    var $btn = $(this);
    var $component = $btn.closest(".aps-team-schedule-results");
    var $pagination = $btn.closest(".aps-schedule-pagination");
    var tabKey = $pagination.attr("data-tab");
    var $list = $component.find(".aps-schedule-list-premium.aps-schedule-tab-list[data-tab=\"" + tabKey + "\"]");
    if (!$list.length) return;
    var pageSize = parseInt($list.attr("data-page-size") || "5", 10);
    var $visible = $list.find(".aps-schedule-item-premium").filter(function () {
      return !$(this).hasClass("aps-schedule-item--filtered");
    });
    var $hidden = $visible.filter(".aps-schedule-item--hidden");
    var $toShow = $hidden.slice(0, pageSize);
    $toShow.removeClass("aps-schedule-item--hidden");
    var remaining = $hidden.length - $toShow.length;
    if (remaining <= 0) {
      $pagination.hide();
    }
  });

  $(document).on("click", ".aps-h2h-load-more", function () {
    var $btn = $(this);
    var $component = $btn.closest(".aps-head-to-head");
    var $list = $component.find(".aps-h2h-list");
    if (!$list.length) return;
    var pageSize = parseInt($list.attr("data-page-size") || "5", 10);
    var $hidden = $list.find(".aps-h2h-item--hidden");
    var $toShow = $hidden.slice(0, pageSize);
    $toShow.removeClass("aps-h2h-item--hidden");
    if ($hidden.length - $toShow.length <= 0) {
      $btn.closest(".aps-h2h-pagination").hide();
    }
  });

  function initScheduleFilters() {
    $(".aps-team-schedule-results").each(function () {
      applyScheduleFilters($(this));
    });
  }

  $(function () {
    initScheduleFilters();
  });

  if (typeof window !== "undefined") {
    window.APSComponentsFrontInit = initScheduleFilters;
  }
})(jQuery);
