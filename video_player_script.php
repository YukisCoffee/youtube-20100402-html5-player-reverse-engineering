<script type="text/javascript">
var playerElementId = "";

var writeFlashEmbed = function() {
    var video_url = "http://www.youtube.com/v/Aabd8UnSuDE?showinfo=0&amp;enablejsapi=1&amp;et=OEgsToPDskILQ15cErDOxzLIRTEYDBeW&amp;hl=pt_BR&amp;el=embedded&amp;version=3&amp;fs=1";
    var fo = new SWFObject(video_url, "movie_player", "100%", "100%", "7", "#000000");
    var startTime = yt.www.watch.player.processLocationHashSeekTime();
    if (window.opener
        && window.opener.yt
        && window.opener.yt.getConfig
        && window.opener.yt.getConfig('SEQUENTIAL_VIDEO_LIST')) {
        fo.addVariable("playlist", window.opener.yt.getConfig('SEQUENTIAL_VIDEO_LIST'));
    }
    fo.addParam("allowFullscreen", "true");
    fo.addParam("AllowScriptAccess", "always");

        fo.addVariable("iurl", "http:\/\/i2.ytimg.com\/vi\/Aabd8UnSuDE\/hqdefault.jpg");
        fo.addVariable("el", "embedded");
        fo.addVariable("pads", "");
        fo.addVariable("hl", "pt_BR");
        fo.addVariable("vq", "");
        fo.addVariable("video_id", "Aabd8UnSuDE");
        fo.addVariable("eurl", "http:\/\/www.youtube.com\/watch?v=Aabd8UnSuDE");
        fo.addVariable("autoplay", "");
    fo.addVariable("autohide", "1");
    if (startTime) {
        fo.addVariable('start', startTime);
    }
    fo.write("watch-player-div");

    playerElementId = "movie_player";

        handleResize = function() {
    var windowHeight = window.innerHeight;
    var adjustedHeight = windowHeight - _gel('watch-longform-ad').offsetHeight;
    var percentHeight = Math.round((adjustedHeight * 100) / windowHeight) + "%";
    _gel('watch-player-div').style.height = percentHeight; 
}
yt.events.listen(window, 'resize', handleResize);
yt.events.listen(_gel('watch-longform-ad-placeholder'), 'resize', handleResize);

}

var writeHtml5Embed = function() {
    //document.getElementById("html5-player-css-holder").innerHTML = "\t<link  rel=\"stylesheet\" href=\"\/s\/yt\/cssbin\/www-player-vfl182987.css\">\n";

    var startTime = yt.www.watch.player.processLocationHashSeekTime();

    var videoPlayer = new yt.player.VideoPlayer();
    window.videoPlayerInst = videoPlayer;

    var playerVars = {"iurl": "http:\/\/i2.ytimg.com\/vi\/Aabd8UnSuDE\/hqdefault.jpg", "el": "embedded", "pads": "", "hl": "pt_BR", "vq": "", "video_id": "Aabd8UnSuDE", "eurl": "http:\/\/www.youtube.com\/watch?v=Aabd8UnSuDE", "autoplay": false};
    if (startTime) {
        playerVars['start'] = startTime;
    }
    videoPlayer.setTargetElementId("video-player");
    videoPlayer.setVideoId("dQw4w9WgXcQ");
    videoPlayer.initialize(startTime, true, true);

    // js api dispatcher
    // Only works now for void fns
    // TODO: figure out how to do callbacks synchronously, and have arguments, probably with JSON

    playerElementId = "video-player";
        handleResize = function() {
    var windowHeight = window.innerHeight;
    var adjustedHeight = windowHeight - _gel('watch-longform-ad').offsetHeight;
    var percentHeight = Math.round((adjustedHeight * 100) / windowHeight) + "%";
    _gel('video-player').style.height = percentHeight; 
}
yt.events.listen(window, 'resize', handleResize);
yt.events.listen(_gel('watch-longform-ad-placeholder'), 'resize', handleResize);


    //add JS API for iframe
    var whitelistApiCalls = {
        'playVideo': true,
        'pauseVideo': true,
        'seekTo': true,
        'mute': true,
        'unMute': true};
    var receiveYtMessage = function(event) {
        var player = document.getElementById(playerElementId);
        var message = event.data;
        var fnName = message && message['f'];
        var argList = (message && message['a']) || [];
        if (whitelistApiCalls[fnName] && player[fnName]) {
            var fn = player[fnName];
            var result;
            if (fn) {
                result = fn.apply(player, argList);
            }
        }
            
        // TODO: figure out how to do callbacks synchronously
        //event.source.postMessage("1234.567", event.origin);
    };
    yt.events.listen(window, 'message', receiveYtMessage);
}

var availableFormats = [];


// var supportsHtml5 = yt.player.VideoFormat.hasSupportedFormats(availableFormats);

writeHtml5Embed();

</script>