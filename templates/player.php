{% extends "default.php" %}

{% block head %}
<link rel="stylesheet" href="/css/player.css" />

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
          {% for score in scores %}
            ['{{ score.raw.date }}', {{ score.rank }}],
          {% endfor %}
        ]);

        // Set chart options
        var options = {'title':'{{ player.name }}\'s rank',
                       'vAxis': { 'direction': -1},};

        // Instantiate and draw our chart, passing in some options.
        var chart = new google.visualization.LineChart(document.getElementById('chart'));
        chart.draw(data, options);
      }
</script>
{% endblock %}

{% block title %}| {{ player.name }}'s profile{% endblock %}

{% block content %}


<!-- Main page -->
<div class="row col-md-12 main center-block">
    <h1><img src="{{ player.avatar_medium }}" class="player-avatar" /> <a href="http://steamcommunity.com/profiles/{{ player.steamid }}">{{ player.name }}</a>'s profile</h1>

<div class="row">
  <div class="col-md-6">
    <div class="col-md-12">
    {% if player.suspected_hacker %}<div class="text-danger"><h3>Suspected hacker</h3> {{ player.name }} is marked as a <span class="label label-danger">Suspected Hacker</span>. <a href="/about" class="text-danger"><b>Click here</b></a> to learn more about the hacker marking process.</div> {% endif %}
    {% if session.admin > 0 %}<div class="text-danger"><h3>Admin tools</h3><p><a href="/admin/player/{{ player.steamid }}/mark">Mark as hacker</a> | <a href="/admin/player/{{ player.steamid }}/unmark">Remove hacker mark</a> | <a href="/admin/player/{{ steamid }}/update">Manual profile update</a></p>
    <p>Note: Marked hackers will keep their scores until the next update.</p></div>{% endif %}
    <h3>All-time rank</h3>
    {% if player.totalrank == -1 %}
    <p>This player is not ranked at the moment, either because they were marked as a suspected hacker or because the scores did not yet update.</p>
    {% else %}
    <p>{{ player.name }} is ranked <b>#{{ player.totalrank }} all-time</b> with <b>{{ player.totalkills }} lifetime kills</b> over <b>{{ player.runs }}</b> runs!</p>
    {% endif %}
      <h3>Daily History</h3>
      <table class="table table-responsive table-hover">
        <thead> 
          <td>Date</td>
          <td>Rank</td>
          <td><abbr title="Player's performance relative to the other runs of that day - e.g., 25% means that the player was in the top 25% of players that day.">Top %</abbr></td>
          <td>Score</td>
          {% if session.admin > 0 %}
          <td class="text-danger">Admin</td>
          {% endif %}
        </thead>
        <tbody>
          {% for score in scores %}
          <tr onclick="document.location = '/score/{{ score.hash}}'" {% if score.hidden %}class="hidden-score"{% endif %} >
            <td>{{ score.raw.date }}</td>
            <td><b>#{{ score.rank }}</b></a></td>
            <td>{{ score.percentage }}%</td>
            <td>{{ score.score }}</td>
            {% if session.admin > 0 %}
            <td><a href="/admin/player/{{ player.steamid }}/score/{{ score.hash }}/delete" class="text-danger">Hide</a></td>
            {% endif %}
          </tr>
          
          {% endfor %}
        </tbody>
        </table>
    </div>
  </div>
  <div class="col-md-5 col-md-offset-1">
  <div class="col-md-12">
    <h3>Rank history</h3>
    <div id="chart"></div>
  </div>
  <div class="col-md-12">
    <h3>Total score</h3>
    <table class="table">
      <tr>
        <td>Most kills</td>
        <td>{{ player.hiscore }}</td>
      </tr>
      <tr>
        <td>Least kills</td>
        <td>{{ player.loscore }}</td>
      </tr>
      <tr>
        <td>Average kills</td>
        <td>{{ player.avgscore }}</td>
      </tr>
      <tr>
        <td>Lowest rank</td>
        <td>{{ player.hirank }}</td>
      </tr>
      <tr>
        <td>Highest rank</td>
        <td>{{ player.lorank }}</td>
      </tr>
      <tr>
        <td>Average rank</td>
        <td>{{ player.avgrank }}</td>
      </tr>
    </table>
  </div>
  </div>
</div>
</div>
{% endblock %}
