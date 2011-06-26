function triviaupdate() {
  $.getJSON('http://play.aadl.org/summergame/triviaupdate', function(json) {
    $("div.autorefresh_div").hide().fadeIn("slow").html(json['html']);
  });
}
