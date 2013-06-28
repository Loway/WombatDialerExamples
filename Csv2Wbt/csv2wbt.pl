#! /usr/bin/perl 

use strict;
use Getopt::Std;

#
# Covert from (siomple) CSV file to WombatDialer input format.
#
# Author: Loway
#
# Options
#  -n XXX   Number stored in field NUM
#  -f num   CSV file to read
#

my %options;
my %fields;
getopts( "n:f:", \%options );

my $fld_num = $options{n} || "NUM";
my $file    = $options{f} || "";

open F, $file or die "$! $file";
my $r = <F>;
chomp $r;

# read header (line 1)
my $pos = 0;
foreach my $fld ( split /,/, $r )  {
	$fields{ $fld } = $pos++;
}

# read actual data (lines 2+)
while ( <F> ) {
	chomp;	
	next if /^\s*$/;	
	my @line = split /,/, $_;

	my @outdata;
	push @outdata, $line[ $fields{$fld_num} ] ;

	foreach my $fld ( keys %fields ) {
		if ( $fld ne $fld_num) {
			my $v = $line[ $fields{$fld} ];
			if ( length( $v ) > 0 ) {
				push @outdata, $fld . ":" . $v;
			}
		}
	}
	print join(  ",", @outdata ) . "\n";
}

