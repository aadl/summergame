function triviaupdate() {
  $.getJSON('http://odie.aadl.org/~kloostere/beta/?q=summergame/triviaupdate', function(json) {
    $("div.autorefresh_div").hide().fadeIn("slow").html(json['html']);
  });
}
