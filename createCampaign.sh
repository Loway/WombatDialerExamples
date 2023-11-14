#! /bin/bash

# Creates a campaign on WombatDialer.
#
# It expects an existing Trunk and an EndPoint


WOMBAT=http://127.0.0.1:8080/wombat
AUTH=

TRUNK=tk
ENDPOINT=ep


# GET

GET=curl -v 


#
# Creates a campaign
#