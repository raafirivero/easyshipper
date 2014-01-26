<?php
			// function to round up to nearest 5 if shipping abroad
			function roundUpToAny($n,$x=5) {
				if($shipping_abroad) {
					return round(($n+$x/2)/$x)*$x;
					} else {
						return $n;
					}
				}