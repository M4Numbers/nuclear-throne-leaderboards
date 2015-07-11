# Nuclear Throne Leaderboards

Created by @Crescentrose  
Tidied & modified by @M4Numbers

## What is a Nuclear Throne Leaderboards?

[Nuclear Throne](http://nuclearthrone.com/) is an early access game currently being developed
by [Vlambeer](http://www.vlambeer.com/) for Steam and the Sony Playstation 4 and PSVita.

It currently features a number of playable characters and is (more-or-less) feature-complete
with several levels and a very large number of guns. In addition to this, it also features
daily challenges (that is to say, a seed which is shared across everyone who plays Nuclear
Throne, allowing them to play the same dungeon) where a number of people all come together
and try to become the #1 player.

However, due to the game being in early access, there is currently no way for players to see
(in detail) who placed where in that daily; which is where this repository steps in. This
represents a website whose sole purpose is to collect the leaderboard statistics from Steam
and to collate them into a leaderboard that everyone can look at.

The live copy of this website is currently running at [thronebutt.com](https://www.thronebutt.com).

## How is Nuclear Throne Leaderboards built?

Nuclear Throne Leaderboards has been built using PHP (surprise, surprise), along with a few
additional vendor items including:

* [Twig/Twig](http://twig.sensiolabs.org/)
* [LightOpenID/LightOpenID](https://github.com/iignatov/LightOpenID)
* [Erusev/Parsedown](http://parsedown.org/)

This allows for the site to be maintained and separated with relative ease. In addition to these
items, the site also makes use of the PDO classes that PHP offers (although at the moment, the
dsn is currently only configured for [mysql](https://www.mysql.com/)/[mariadb](https://www.mariadb.com/).

The site works on a centralisation front, where everything goes through the index page (yes, I
do mean everything, I checked*). This means that, at this moment, this is a very hard site to
port anywhere. It requires a rooted host (so you can't hide it in a directory), and for Apache
to allow overrides in its directory.

\* Everything except for the things in the /updater/ directory

## What's Happening Right Now?

Currently, the repository is going through a multi-stage improvement and tidy-up process, carried
out by @M4Numbers, who is going through and enforcing some general standards that will make the
code base nicer to work with in the future.

For more information on this, please look at the UPDATING.md file in the same directory as this.

## Installation

To Be Completed!

This file was written by @M4Numbers and may contain some erroneous information about the orignal
codebase. If you spot any errors with it, please update the readme.
