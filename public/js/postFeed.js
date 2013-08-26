windowIntervals = [];

function clearWindowIntervals() {
  for(var i = 0; i < windowIntervals.length; i++) {
    window.clearInterval(windowIntervals[i]);
  }
  windowIntervals = [];
}

function bindTopicUserIDsToButton(topicID, userID) {
  //sets topicID and userID to the hidden entries button.
  $('#hiddenEntriesButton').attr('topicID', topicID);
  $('#hiddenEntriesButton').attr('userID', userID);
}

function bindExpandTextLinks() {
  //binds a click event to hide textblurbs and show full texts.
  $('.feedBlurb.bindExpandEvent').each(function() {
    $(this).removeClass('bindExpandEvent');
    $(this).click(function() {
      $(this).slideToggle();
      $(this).parent().find('.feedHiddenText').slideToggle();
    });
    $(this).parent().find('.feedHiddenText').click(function() {
      $(this).slideToggle();
      $(this).parent().find('.feedBlurb').slideToggle();
    });
  });
}

function setLLPostFeedLoadEvent(topicID, userID) {
  windowIntervals.push(window.setInterval(function() {
    $.ajax({
      url: 'fetchLLPostFeed.php?lastTime=' + encodeURIComponent($('.feedDate:first').attr('time')) + '&topicID=' + ((topicID != "" && !isNaN(parseInt(topicID))) ? parseInt(topicID) : "") + '&userID=' + ((userID != "" && !isNaN(parseInt(userID))) ? parseInt(userID) : "")
    }).done(function(data) {
      if (data.length > 0) {
        if ($('body').attr('blurred') == "true" || $('#hiddenEntriesButton').is(':visible')) {
          $('#hiddenEntriesButton').attr('entryCount', parseInt($('#hiddenEntriesButton').attr('entryCount')) + ($(data).length + 1)/2);
          $('#hiddenEntriesButton').text('Show ' + $('#hiddenEntriesButton').attr('entryCount') + ' new entries');
          $('#hiddenEntriesButton').slideDown();
          document.title = "(" + $('#hiddenEntriesButton').attr('entryCount') + ") " + document.title.replace(/\([0-9]+\)\ /i, '');
          $($(data).get().reverse()).each(function() {
            $('#malActivityFeed').prepend($(this).hide());
          });
        } else {
          $($(data).get().reverse()).each(function() {
            $('#malActivityFeed').prepend($(this).hide().fadeIn(1500));
          });
        }
        bindExpandTextLinks();
      }
    });
    updateFeedTimes();
  }, 10000));
}

function showHiddenItems() {
  clearWindowIntervals();
  $("#malActivityFeed").find(".feedItem:hidden").each(function() {
    $(this).fadeIn(1500);
  });
  $("#hiddenEntriesButton").slideUp();
  $("#hiddenEntriesButton").attr('entryCount', 0);
  setLLPostFeedLoadEvent($("#hiddenEntriesButton").attr('topicID'), $("#hiddenEntriesButton").attr('userID'));
}

function getLLInfoPane(topicID, userID) {
  //loads information for the LL info pane.
  $("#malInfoPane").html("");
  if (topicID == "" && userID == "") {
    $("#malInfoPane").hide();
    $("#feedColumn").removeClass("rightColumn").addClass("leftColumn");
    $("#hotAnimeList").show();
    return;
  } else {
    $("#feedColumn").removeClass("leftColumn").addClass("rightColumn");
    $("#hotAnimeList").hide();
    $("#malInfoPane").show();
    var ajax_load = "<img class='loader' src='ajaxLoader.gif' alt='Loading...' />";
    $("#malInfoPane").html(ajax_load);  
  }
  $.ajax({
    url: 'fetchLLInfoPane.php?' + 'topicID=' + ((topicID != "" && !isNaN(parseInt(topicID))) ? parseInt(topicID) : "") + '&userID=' + ((userID != "" && !isNaN(parseInt(userID))) ? parseInt(userID) : "")
  }).done(function(data) {
    $("#malInfoPane").html(data);
    renderGraphs();
  });
}

function getLLPostFeed(topicID, userID) {
  //loads the first few items in the MAL activity feed and sets an update event to load more.
  var ajax_load = "<img class='loader' src='ajaxLoader.gif' alt='Loading...' />";
  clearWindowIntervals();
  $("#malActivityFeed").html(ajax_load);
  $("#hiddenEntriesButton").hide().attr('entryCount', 0);
  bindTopicUserIDsToButton(topicID, userID);
  getLLInfoPane(topicID, userID);
  $.ajax({
    url: 'fetchLLPostFeed.php?lastTime=0' + '&topicID=' + ((topicID != "" && !isNaN(parseInt(topicID))) ? parseInt(topicID) : "") + '&userID=' + ((userID != "" && !isNaN(parseInt(userID))) ? parseInt(userID) : "")
  }).done(function(data) {
    $("#malActivityFeed").html(data);
    bindExpandTextLinks();
  });
  setLLPostFeedLoadEvent(topicID, userID);
  $(window).unbind("scroll");
  $(window).scroll(function() {
    if ($('#malActivityFeed li').length > 0 && $('body').height() <= ($(window).height() + $(window).scrollTop()) && !alreadyLoading) {
      //get last-loaded MAL change and load more past this.
      alreadyLoading = true;  
      var lastTime = $('.feedDate:last').attr('time');
      $.get('fetchLLPostFeed.php?olderThan=' + encodeURIComponent(lastTime) + '&topicID=' + ((topicID != "" && !isNaN(parseInt(topicID))) ? parseInt(topicID) : "") + '&userID=' + ((userID != "" && !isNaN(parseInt(userID))) ? parseInt(userID) : ""), function(html) {
        $(html).each(function() {
          $('#malActivityFeed').append($(this).hide().fadeIn(1500));
        });
        bindExpandTextLinks();
        alreadyLoading = false;
      });
    }
  });
}

function getTrendingWords() {
  //loads the "trending words" list to the right of the post feed.
  var ajax_load = "<img class='loader' src='ajaxLoader.gif' alt='Loading...' />";
  $("#hotAnimeList").html(ajax_load);
  $.ajax({
    url: 'fetchTrendingWords.php'
  }).done(function(data) {
    $("#hotAnimeList").html(data);
  });
}

function loadLLPostFeeds(topicID, userID) {
  //loads all the feeds on a page and sets the proper window events.
  $(window).unbind("scroll");
  $(window).unbind("blur");
  $(window).unbind("focus");
  $(window).blur(function() {
    $('body').attr('blurred', true);
  });
  $(window).focus(function() {
    $('body').attr('blurred', false);
    window.setTimeout(function() {
      document.title = document.title.replace(/\([0-9]+\)\ /i, '');
    }, 200);
  });
  $("#hiddenEntriesButton").hide().attr('entryCount', 0);
  $("#feedColumn").removeClass("rightColumn quartColumn").addClass("leftColumn halfColumn");
  $("#hotAnimeList").removeClass("leftColumn halfColumn").addClass("rightColumn quartColumn");
  $("#malInfoPane").removeClass("rightColumn quartColumn").addClass("leftColumn halfColumn");
  
  getLLPostFeed(topicID, userID);
  getTrendingWords();
  getLLInfoPane(topicID, userID);
}