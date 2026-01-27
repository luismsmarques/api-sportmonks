document.addEventListener('DOMContentLoaded', function () {
  var widgets = document.querySelectorAll('.aps-next-game');
  if (!widgets.length) {
    return;
  }

  widgets.forEach(function (widget) {
    var start = widget.getAttribute('data-start');
    var output = widget.querySelector('.aps-countdown');
    if (!start || !output) {
      return;
    }

    var startDate = new Date(start.replace(' ', 'T') + 'Z');
    if (isNaN(startDate.getTime())) {
      output.textContent = '';
      return;
    }

    function tick() {
      var now = new Date();
      var diff = startDate.getTime() - now.getTime();
      if (diff <= 0) {
        output.textContent = 'A iniciar';
        return;
      }

      var seconds = Math.floor(diff / 1000);
      var days = Math.floor(seconds / 86400);
      var hours = Math.floor((seconds % 86400) / 3600);
      var minutes = Math.floor((seconds % 3600) / 60);
      var secs = seconds % 60;

      output.textContent =
        days + 'd ' + hours + 'h ' + minutes + 'm ' + secs + 's';
    }

    tick();
    setInterval(tick, 1000);
  });
});
