# Battleships
Code for playing Mulesofts Battleships API

At [API Days 2016](https://apidays.nz) Mulesoft issued a challenge to score the highest ratio of hits/misses against their Battleships API.

The attached script was written between 8pm and 2am at which point it was clear the approach was working and I left the system to run. 

The API has since shutdown but the approach may be useful. It consists of two users. One to brute force the playing board hitting every second square.
The second is used to follow up on any hits therefore gaining a higher hit rate than a regular random user or brute force attack. Finally the brute force user is used to sweep the board for any hiding 1x1 boats.

Additonal run modes allow for reporting of statistics, clearing the board or displaying the board state.

I was in the process of multithreading the code when I determined that I had a sufficient lead that I could get some sleep and still win, so I did and I did. The code as not been edited since then except to remove credentials.