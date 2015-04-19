{% extends "default.php" %}

{% block head %}
<!--Load the chart API-->
<script type="text/javascript" src="https://www.google.com/jsapi"></script>
<script type="text/javascript">
      // Load the Visualization API and the piechart package.
      google.load('visualization', '1.0', {'packages':['corechart']});

      // Set a callback to run when the Google Visualization API is loaded.
      google.setOnLoadCallback(drawChart);

      // Callback that creates and populates a data table,
      // instantiates the pie chart, passes in the data and
      // draws it.
      function drawChart() {

        // Create the data table.
        var data = new google.visualization.DataTable();
        data.addColumn('string', 'Date');
        data.addColumn('number', 'Rank');
        data.addRows([
          {% for score in scores_graph %}
            ['{{ score.raw.date }}', {{ score.rank }}],
          {% endfor %}
        ]);

        // Set chart options
        var options = {'title':'{{ player.name }}\'s rank',
                       'vAxis': { 'direction': -1}};

        // Instantiate and draw our chart, passing in some options.
        var chart = new google.visualization.LineChart(document.getElementById('chart'));
        chart.draw(data, options);
      }
</script>

<meta name="steamid" content="{{ player.steamid }}" />
{% endblock %}

{% block title %}| {{ player.name }}'s profile{% endblock %}

{% block content %}
<!-- Main page -->
<div class="row">
  <div class="col-md-8 leaderboard">
    <div class="inner">
      <div class="row palace-wall">
        <div class="col-md-12">
          <h3 class="title stroke-hard player-title">
            <img src="{{ player.avatar_medium }}" class="player-avatar" />
            <a href="http://steamcommunity.com/profiles/{{ player.steamid }}">{{ player.name }}</a>'s profile
          </h3>
        </div>
      </div>

      <div class="row palace-floor">
        <div class="col-md-12">
          <h3 class="profile-subtitle stroke">Best moments</h3>
          <h5 class="profile-subtitle stroke">Top ranks and highscores</h5>
          <table class="table table-responsive table-hover">
            <thead> 
              <td>Date</td>
              <td>
                <abbr title="Player's performance relative to the other runs of that day - e.g., 25% means that the
                player was in the top 25% of players that day.">
                  Top %
                </abbr>
              </td>
              <td>Rank</td>
              <td>Score</td>
            </thead>
            <tbody>
              {% for score in best_moments %}
              <tr {% if score.hidden %}class="hidden-score"{% endif %} >
                <td>{{ score.raw.date }}</td>
                <td>{{ score.percentile }}%</td>
                <td><b>#{{ score.rank }}</b></td>
                <td><b>{{ score.score }}</b></td>
              </tr>
              {% endfor %}
            </tbody>
          </table>
          <h3 class="profile-subtitle stroke">Score history</h3>
          <h5 class="profile-subtitle stroke">Latest runs and scores - Click on a score to view details!</h5>
          <table class="table table-responsive table-hover">
            <thead> 
              <td>Date</td>
              <td>
                <abbr title="Player's performance relative to the other runs of that day - e.g., 25% means that the
                player was in the top 25% of players that day.">
                  Top %
                </abbr>
              </td>
              <td>Rank</td>
              <td>Score</td>
            </thead>
            <tbody id="latest_score_table">
              {% for score in scores %}
              <tr {% if score.hidden %}class="hidden-score"{% endif %} >
                <td>{{ score.raw.date }}</td>
                <td>{{ score.percentile }}%</td>
                <td><b>#{{ score.rank }}</b></td>
                <td><b>{{ score.score }}</b>
                  {% if score.raw.video %}
                    <span class="pull-right"><a href="{{ score.raw.video }}"><img src="/img/youtube.png" alt="Video link" title="There's a video attached to this score." /></a></span>
                  {% endif %}
                    <span class="pull-right"><a href="/score/{{ score.hash }}"><span class="glyphicon glyphicon-plus more-link"></span></a></span>
                </td>
              </tr>
              {% endfor %}
            </tbody>
          </table>
          <center><button id="nextPageBtn" class="btn btn-retro">Older scores</button></center>
        </div>
      </div>
    </div>
  </div>

  <div class="col-md-4 sidebar">
    <div class="sidebar-box">
      <div class="row mansion-wall">
        <div class="col-md-12">
          <h4 class="title stroke sidebar-title">About player</h4>
        </div>
      </div>
      <div class="row mansion-floor">
        <div class="col-md-12">
          <div class="badges">
            {% if player.raw.wins > 0 %}
              <div class="tbbadge">
                <span class="crown  ">
                  <img src="/img/big-crown.png" alt="Previous wins" data-toggle="tooltip" data-placement="right" title="This 
                  player has won on {{ player.raw.wins }} day(s)!" />
                  <span class="wins-big stroke">{{ player.raw.wins }}</span>
                </span>
              </div>
            {% endif %}
            <div class="tbbadge">
              <span class="crown">
                <img src="/img/kills.png" alt="Total pts" data-toggle="tooltip" data-placement="right" title="This 
                player has {{ total.sum }} total kills!" />
                <span class="wins-big stroke nudge-left">{{ total.ksum }}</span>
              </span>
            </div>
            <div class="tbbadge" data-toggle="tooltip" data-placement="bottom" title="This 
                player did {{ total.count }} daily runs!">
              <span class="crown">
                <img src="/img/runs.png" alt="Total runs"/>
                <span class="wins-big stroke nudge-left">{{ total.count }}</span>
              </span>
            </div>
          </div>
          <p><span class="stat-title stroke">Average score:</span><br/><span class="stat-value stroke">{{ total.average }}</span></p>
          <p><span class="stat-title stroke">Average of 10 best:</span><br/><span class="stat-value stroke">{{ total.average_top10 }}</span></p>
        </div>
      </div>
    </div>
  </div>
</div>
<script src="/js/profile.js"></script>
  {% endblock %}
