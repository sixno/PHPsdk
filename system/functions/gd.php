<?php if(!defined('SYSTEM')) exit('No direct script access allowed');

function imagefilledroundrect($image,$radius,$x1,$y1,$x2,$y2,$color)
{
	imagefilledellipse($image,$x1+$radius,$y1+$radius,2*$radius,2*$radius,$color);
	imagefilledellipse($image,$x2-$radius,$y1+$radius,2*$radius,2*$radius,$color);
	imagefilledellipse($image,$x1+$radius,$y2-$radius,2*$radius,2*$radius,$color);
	imagefilledellipse($image,$x2-$radius,$y2-$radius,2*$radius,2*$radius,$color);

	imagefilledrectangle($image,$x1+$radius,$y1,$x2-$radius,$y2,$color);
	imagefilledrectangle($image,$x1,$y1+$radius,$x2,$y2-$radius,$color);
}

function imageroundrect($image,$radius,$x1,$y1,$x2,$y2,$color)
{
	imagearc($image,$x1+$radius,$y1+$radius,2*$radius,2*$radius,180,270,$color);
	imagearc($image,$x2-$radius,$y1+$radius,2*$radius,2*$radius,270,360,$color);
	imagearc($image,$x1+$radius,$y2-$radius,2*$radius,2*$radius, 90,180,$color);
	imagearc($image,$x2-$radius,$y2-$radius,2*$radius,2*$radius,  0, 90,$color);

	imageline($image,$x1+$radius,$y1,$x2-$radius,$y1,$color);
	imageline($image,$x1+$radius,$y2,$x2-$radius,$y2,$color);
	imageline($image,$x1,$y1+$radius,$x1,$y2-$radius,$color);
	imageline($image,$x2,$y1+$radius,$x2,$y2-$radius,$color);
}

function imageborderedroundrect($image,$radius,$x1,$y1,$x2,$y2,$color,$border_width)
{
	for($i = 0;$i < $border_width;$i++)
	{
		imageroundrect($image,$radius-$i,$x1+$i,$y1+$i,$x2-$i,$y2-$i,$color);
	}
}

function imageborderedellipse($image,$cx,$cy,$width,$height,$color,$border_width)
{
	$width  -= 2;
	$height -= 2;

	$border_width *= 2;

	for($i = 0;$i < $border_width;$i+=2)
	{
		imageellipse($image,$cx-1,$cy-1,$width-$i,$height-$i,$color);
		imageellipse($image,$cx-1,$cy,$width-$i,$height-$i,$color);
		imageellipse($image,$cx,$cy-1,$width-$i,$height-$i,$color);
		imageellipse($image,$cx,$cy,$width-$i,$height-$i,$color);
	}
}

function imageborderedrectangle($image,$x1,$y1,$x2,$y2,$color,$border_width)
{
	for($i = 0;$i < $border_width;$i++)
	{
		imagerectangle($image,$x1+$i,$y1+$i,$x2-$i,$y2-$i,$color);
	}
}

?>