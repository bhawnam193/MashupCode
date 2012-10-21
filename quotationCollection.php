<?php

	/**
	 * This class is for creating mashups out of collections of quotations, it is a subclass of musicCollection as many of my quotations are song lyrics
	 *
	 * @author Muskie McKay <andrew@muschamp.ca>
     * @link http://www.muschamp.ca
     * @version 1.0
	 * @copyright Muskie McKay
	 * @license MIT
	 */
	
	/**
		The MIT License
	
		Copyright (c) 2010 Andrew "Muskie" McKay
	
		Permission is hereby granted, free of charge, to any person obtaining a copy
		of this software and associated documentation files (the "Software"), to deal
		in the Software without restriction, including without limitation the rights
		to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
		copies of the Software, and to permit persons to whom the Software is
		furnished to do so, subject to the following conditions:
	
		The above copyright notice and this permission notice shall be included in
		all copies or substantial portions of the Software.
	
		THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
		IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
		FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
		AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
		LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
		OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
		THE SOFTWARE.
	 */
	
	require_once('musicCollection.php');

	class quotationCollection extends musicCollection
	{
		
		/**
		* Initialize a Quotation Collection
		*
		*  I'm not sure if this is required or if I will do anything, the original constructor was always designed to handle various ways
		* of getting the data into the class, from a CSV file to passing it in as an array to eventually database access...
		*
		* @param input can vary and what type determines how the class is initialized/created see parent method.
		*/
		public function __construct($input)
		{
			parent::__construct($input);
		}
	
	
		
		/**
		* Initialize a Quotation Collection
		*
		*  I haven't overiden  the constructer, but by overiding this method, I can more easily add new APIs, which I currently don't do...
		*/
		protected function initializeAPIs()
		{
			parent::initializeAPIs();
		}
		
		
		
		/**
		 * This is a method that shouldn't be called much as it is a brute force dump of the entire quotation collection
		 * linking to the Wikipedia for the source, when possible.
		 * 
		 */
		 public function outputEntireCollection()
		 {
			if($this->hasMembers())
			{
				// Highly likely we find something in some database in this case
				print("<ul>");
			}
		 
			foreach($this->theCollection as $quotationInfo)
			{
				$results = $this->searchWikipediaFor($quotationInfo[0]);  // Will eventually possibly do something more clever
				if ($results->Section->Item != NULL)
				{
					print('<li>');
					print('<a href="' . $results->Section->Item->Url . '">' . $quotationInfo[0] . '</a>:');
					print('<br />');
					print('<blockquote>');
					print($quotationInfo[1]);
					print('</blockquote>');
					print('</li>');
				}
			}
			
			if($this->hasMembers())
			{
				print("</ul>");
			}
		 }
         
         
         
        /**
         * Searches for a random collection member, I use the time stamp in order to eliminate repeats when someone browses the entire collection.
         * They'll still miss some and it will wrap around but I think it is good enough.  I no longer look for details, if I can't find a picture 
         * it isn't the end of the world.
         *
         * @return the currentMember as array 
         */
         public function randomQuotation()
         {	
         	$seedNumber = time();
         	$randomNumber = $seedNumber % $this->collectionSize();
         	$this->currentMemberIndex = $randomNumber;
         	$aMember = $this->currentMemberAsArray();
         
         	return $aMember;
         }
         
         
         
       	 /**
       	 * This method like many before it, searches Amazon's Product API for information about the quotation described in the array.
       	 * I use a third field/column to give hints on what too look for in Amazon and other APIs.  I also cache the results and in some
       	 * cases we may have the information we are looking for already cached locally.  A lot of information can be returned, we usually just use the
       	 * first item ie $result->Items->Item[0]
       	 *
       	 * @param array
       	 * @return Simple XML object
       	 */
         private function getInfoFromAmazonFor($quotation)
         {   
         	if(count($quotation) >= 2)
         	{
				$validFilename = preg_replace("/[^a-zA-Z0-9]/", "", $quotation[0]);
	
				if(is_array($quotation) && strlen($validFilename) > 0)
				{
					$myCache = new Caching("./MashupCache/Amazon/", $validFilename, 'xml');
					
					if ($myCache->needToRenewData())
					{		
						try
						{
							// Going to have a three (or eventually more) pronged approach that will require new methods in the Amazon API class.
							if($this->isFromFilm($quotation))
							{
								// We have a quotation from a movie
								$result = $this->amazonAPI->getDVDCoverByTitle($quotation[0]);
							}
							else if($this->isFromSong($quotation))
							{
								// We have a quotation from a song thus by a songwriter
								$result = $this->amazonAPI->getInfoForSongwriter($quotation[0]);
							}
							else
							{
								$result = $this->amazonAPI->getBookForKeyword($quotation[0]);
							}
						}
						catch(Exception $e)
						{	
							echo $e->getMessage();
						}
						$myCache->saveXMLToFile($result);  // Save new data before we return it to the caller of the method 
					}
					else
					{
						// It doesn't need to be renewed so use local copy
						$result = $myCache->getLocalXML();
					}
				}
				else
				{
					throw new Exception('Incorrect data type passed to getInfoFromAmazonFor()');
				}
			}
			else
			{
				$result = null;  // No info found in Amazon 
			}
	
			
			return $result;
         }
         
          
         
        /**
         * Returns the results from a querry to the Amazon Product API for the current quotation 
         *
         * @return XML Object from Amazon.com
         */
         public function getInfoFromAmazonForCurrentQuotation()
         {
         	return $this->getInfoFromAmazonFor($this->currentMemberAsArray());
         }
         
         
         
        /** 
         * Searches the Wikipedia for the passed in quotation in array format.  I use the third column to find better results hopefully.
         *
         * @param array representing quotation 
         * @return Simple XML object 
         */
        protected function searchWikipediaForQuotation($quotation)
        {
        	// Should this method be called searchWikipediaFor($quotation)? no one reviews my code anymore and PHP has such poor naming convention adherence
			// Once again we have a three (or eventually more) pronged approach for finding the best match with the quotation's author
			
			if($this->isFromFilm($quotation))
			{
				// We have a quotation from a movie
				$searchString = $quotation[0] . ' (film)';
				$result = $this->searchWikipediaFor($searchString);
			}
			else if($this->isFromSong($quotation))
			{
				// We have a quotation from a song thus by a songwriter
				// Appending songwriter to the search doesn't result in any results being returned...
				$result = $this->searchWikipediaFor($quotation[0]);
			}
			else
			{ 
				$result = $this->searchWikipediaFor($quotation[0]);
			}
			
			return $result;
        }
        
        
        
    	/** 
         * Searches the Wikipedia for the current quotation
         *
         * @return Simple XML object 
         */
        public function wikipediaInfoForCurrentQuotation()
        {
        	return $this->searchWikipediaForQuotation($this->currentMemberAsArray());
        }
        
        
        
        // subclass can't use this so override 
        public function audioSampleForCurrentAlbumsFavouriteSong()
        {
        	throw new Exception('quotationCollection.php does not support the method audioSampleForCurrentAlbumsFavouriteSong');
        }
        
        
        
        // this subclass can't use this method either
        public function lastFMAlbumTags()
        {
            throw new Exception('quotationCollection.php does not support the method lastFMAlbumTags');
        }
        
        
        // This next method was stolen from movieCollection.php as I also quote from films frequently
      /**
	   * This method searches the IMDB (using the unofficial API) for the passed in film title.  I return the entire SimpleXML Object
	   *
	   * @param string the search query
	   * @return the number one result for the search in IMDB
	   */
	   protected function searchIMDBForFilm($filmTitle)
	   {
	   		$movieInfo = NULL;
	   
			// A lot of people have wanted to search or scrape the IMDB with an API or PHP.  Amazon owns the website so it is possible some of this 
			// data is in the main Amazon Product Advertising API.  I've already noticed how Wikipedia and Rotten Tomatoes occaisionally have the same 
			// exact text.
			
			// The default is to return data in JSON format, but XML is also supported.  Here is what a query URL should look like:
			// http://www.imdbapi.com/?t=True Grit&y=1969 
			// spaces need to be encoded.
			
			// This works well as I always pass in titles of films that I've already found in Rotten Tomatoes, this isn't an official precondition and 
			// I should probably cache the results just like so much other data.
			
			// first check that we don't have a local chached version, no reason to get lazy
			$properFileName = preg_replace("/[^a-zA-Z0-9]/", "", $filmTitle);
			
			if(strlen($properFileName) > 0)
			{
				$myCache = new Caching("./MashupCache/IMDB/", $properFileName);
				
				if ($myCache->needToRenewData())
				{
					try
					{
						$encodedTitle = urlencode ( $filmTitle );
						$queryURL = 'http://www.imdbapi.com/?t=' . $encodedTitle . '&plot=full';  // I prefer the long version of the plot
						$queryResult = fetchThisURL($queryURL);
						$movieInfo = json_decode($queryResult);
					}
					catch(Exception $e)
					{	
						echo $e->getMessage();  
					}
					
					$serializedObject = serialize($movieInfo);
					$myCache->saveSerializedDataToFile($serializedObject);
				}
				else
				{
					// It doesn't need to be renewed so use local copy
					$movieInfo =  $myCache->getUnserializedData();
				}
			}

			return $movieInfo;
	   }
	   
	   
	   
	    /**
		 * Returns the source of the current quotation
		 *
		 * @return string
		 */
		 public function currentQuotationSource()
		 {
		 	return $this->currentArtist();
		 }
		 
		 
		 
		/**
		 * Returns the current quotation ie the second cell in the array
		 *
		 * @return string
		 */
		 public function currentQuotation()
		 {
		 	$arrayVersion = $this->currentMemberAsArray();
		 	
		 	return $arrayVersion[1];
		 }
		 
		 
		 
		/**
		 * Returns the current quotation ie the second cell in the array, with all HTML tags removed.
		 *
		 * @return string
		 */
		 public function currentQuotationWithoutHTML()
		 {
		 	$arrayVersion = $this->currentMemberAsArray();
		 	
		 	return strip_tags($arrayVersion[1]);  // Tempted to leave line breaks in...
		 }
		 
		 
		 
		/**
		 * Returns the hint regarding the type and source of the current quotation, ie the third column/cell
		 *
		 * @return string
		 */
		 public function currentQuotationType()
		 {
		 	$arrayVersion = $this->currentMemberAsArray();
		 	
		 	return $arrayVersion[2];
		 }
	   
	   
	   
	    /**
	     * Returns the length of the current quotation after all HTML tags have been removed 
	     *
	     * @return int 
	     */
	    public function currentQuotationActualLength()
	    {
	    	return strlen($this->currentQuotationWithoutHTML());
	    }
	   
	   
	   
		/**
		 * Returns true if the current quotations length with HTML tags removed is well below 140 characters.
		 * It needs to be well below as the Tweet This button will add in an abreviated URL among other things.
		 * Still hold out hope for a longer tweet button...
		 *
		 * @return bool 
		 */
		public function isCurrentQuotationTweetable()
		{
			// The tweet this button will add a link like:
			// http://t.co/uO4Piib
			// That is 18 characters long, plus white space, call it 20, so quotations need a length of less than 120 characters are tweetable.
			
			// I've decided to try tweeting longer quotations, tweeting the first say 100 characters adding a " to the front and a ... to the back
			
			$isTweetable = false;
			
			if ($this->currentQuotationActualLength() < 120)
			{
				$isTweetable = true;
			}
			
			return $isTweetable; 
		}
		 
	   
	   
	  /**
	   * This method looks at a variety of sources in descending priority to find a decent sized image of the author/source of the quotation 
	   *
	   * @return string representing URL to image 
	   */
	   public function authorImageForCurrentQuotation()
	   {
	   		$imageURL = NULL;
	   		// Wikipedia doesn't have very useful images returned in their API.  
	   		// Amazon is alright but not perfect for people, works fine for CD and DVD covers though...
	   		// Switched to smaller images in some cases, could easily switch back.
	   		
	   		if($this->isCurrentQuotationFromAFilm())
			{
				// Is Amazon better than IMDB for cover image probably.
				$amazonXML = $this->getInfoFromAmazonForCurrentQuotation();
				$imageURL = $amazonXML->Items->Item->MediumImage->URL;  // Was LargeImage, throws error for "Fitzcaraldo"
			}
			else if($this->isCurrentQuotationFromASong())
			{
				// We have a quotation from a song thus by a songwriter
				// Since I changed the parent class, I'm going to use Last.fm for this image.
				$lastFMImageArray = $this->getCurrentArtistPhotoFromLastFM();
				$imageURL = $lastFMImageArray['largeURL'];  
			}
			else
			{
				// We have a quotation by a person or ficticious person
				// Now I'm worried Amazon will return strange stuff and Wikipedia's image is too small, why not Flickr?
				// Flickr returns strange results for long dead people especially...
				$amazonXML = $this->getInfoFromAmazonForCurrentQuotation();
				if ((( ! empty($amazonXML)) 
					&& ($amazonXML->Items->TotalResults > 0)))
				{
					$imageURL = $amazonXML->Items->Item->MediumImage->URL;  // Was LargeImage
				}
			}
			
			if(empty($imageURL))
			{
				// No image so far try flickr!
				// This probably ensures something is always returned for every quotation
				$flickrResults = $this->getCurrentArtistPhotosFromFlickr();
				if ( ! empty($flickrResults['photo']))
				{
					$imageURL = $flickrResults['photo'][0]['url_s'];  // was url_m
					// Got Undefined Offset error once...
					// May have to surround this with a try catch construct
				} 
			}
			
			return $imageURL;
	   }
	   
	   
	   
	   // The next three methods were borrowed and adapted from dvdCollection.php because again lots of quotations are from films 
	   
	    /**
         * Returns the current quotation's ASIN which is a unique identifier used for Amazon.com in their webstore.  
         *
         * @return String
         */
         private function currentQuotationASIN()
         {
         	$dvdASIN = null;
         	
         	$dvdXML = $this->getInfoFromAmazonForCurrentQuotation();
         	
         	if($dvdXML->Items->TotalResults > 0) 
         	{
         		$dvdASIN = $dvdXML->Items->Item->ASIN;
         	}
         	
         	return $dvdASIN;
         }
         
         
         
        /**
         * This method just returns the URL to the product page using the ASIN and will append on your Amazon Associate ID so you can
         * potentially earn a commision.  If the item isn't in Amazon, well return the hash symbol which just reloads the page when clicked...
         *
         * @return string;
         */
         public function currentQuotationAmazonProductURL()
         {
         	$asin = $this->currentQuotationASIN();
         	
         	if($asin != null)
         	{
         		// I'm thinking of doing something a lot clever, as Amazon has stores for major artists like Bob Dylan and Gordon Lightfoot, of course,
         		// I wouldn't get any referral income, but then I don't get any right now as things are...
         		$amazonProductURL = $this->amazonProductURLFor($asin);
         	}
         	else
         	{
         		$amazonProductURL = "#"; // return hash instead of null or empty string so it just reloads the page
         	}
         	
         	return $amazonProductURL;
         }
         
         
         
        /**
         * Returns a string consisting of a link and an image (icon) to the product on Amazon.com, I decided to return valid HTML as I thought this
         * would save some time later on and for some services it is much more work to get the correct info and link to work.  The link returned has
         * an Amazon Associate tag as detailed here: 
         * http://www.kavoir.com/2009/05/build-simple-amazon-affiliate-text-links-with-just-asin-10-digit-isbn-and-your-amazon-associate-tracking-id.html
         *
         * @return string;
         */
         public function currentQuotationAmazonAssociateBadge()
         {
            $htmlTag = NULL;
            
            // This can be a lot clever based on what type of quotation it is...
            
            if($this->isCurrentQuotationFromASong())
            {
            	// This is where I could get clever...
            	// What I need is an API to this:
            	// https://artistcentral.amazon.com/?ref=aspfaq
            	$amazonProductURL = $this->currentQuotationAmazonProductURL();
            }
            else
            {
            	$amazonProductURL = $this->currentQuotationAmazonProductURL();
            }
         	
         	
         	if(strcmp($amazonProductURL, "#") != 0)
         	{
         		$openLinkTag = '<a href="' . $amazonProductURL . '" >';
         		$closeLinkTag = '</a>';
         		$iconTag = '<img src="' . myInfo::AMAZON_ICON_URL . '" class="iconImage" />';
         		
         		$htmlTag = $openLinkTag . $iconTag . $closeLinkTag;
         	}
         	
         	return $htmlTag;
         }
         
         
         
        /**
         * This method searches various APIs to find a short bunch of text describing the source of the quotation 
         *
         * @return string 
         */
         public function currentQuotationSourceBio()
         {
         	$bio = NULL;
	   		
	   		if($this->isCurrentQuotationFromAFilm())
			{
				// We have a quotation from a movie, I like the IMDB for the longer synopsis 
				$imdbInfo = $this->searchIMDBForFilm($this->currentQuotationSource());
				$bio = $imdbInfo->Plot;
			}
			else if($this->isCurrentQuotationFromASong())
			{
				// We have a quotation from a song thus by a songwriter
				// Going with Last.fm here which might just use the description in Wikipedia anyway...
				$lastFMInfo = $this->getArtistInfoFromLastFM($this->currentQuotationSource());
				$bio = $lastFMInfo["bio"]["summary"];  // Possibly emtpy for obscure artists, but I think I'm alright
			}
			else
			{
				// Wikia descriptions are so brief, tempted to try Amazon...
				$wikiXML = $this->wikipediaInfoForCurrentQuotation();
				$bio = $wikiXML->Section->Item->Description;
			}
			
			if(empty($bio))
			{
				// This happens sometimes
				$wikiXML = $this->wikipediaInfoForCurrentQuotation();
				$bio = $wikiXML->Section->Item->Description;
			}
			
			return $bio;
         }
         
         
         
        /**
         * This method returns a Facebook Like button for the most likely page for the source of the current quotation.
         *
         * @return string of valid HTML
         */
         public function facebookLikeButtonForCurrentQuotationSource()
         {
         	// I've slowly started using the fancier like buttons which require additional data to be put inside the <head> portion of a webpage
         	return $this->facebookLikeButtonFor($this->currentQuotationSource());
         }
         
         
        
        /**
         * NOT IMPLEMENTED!
         *
         * This method returns a new style Facebook Like button which should allow people to share the entire quotation, plus author and a link 
         * with their friends on Facebook. It doesn't use an iFrame and Facebook has a second bit of HTML you are supposed to put anywhere on your 
         * page but they recommend just inside the <body> tag.
         *
         * @return string of valid HTML 
         */
         public function facebookLikeButtonForCurrentQuotation() 
         {
			// There is more code that needs to be included, they want it just under the <body> tag, but it can appear elsewhere...
			/*
			<div id="fb-root"></div>
			<script>(function(d, s, id) {
			  var js, fjs = d.getElementsByTagName(s)[0];
			  if (d.getElementById(id)) return;
			  js = d.createElement(s); js.id = id;
			  js.src = "//connect.facebook.net/en_US/all.js#xfbml=1&appId=139511426095383";
			  fjs.parentNode.insertBefore(js, fjs);
			}(document, 'script', 'facebook-jssdk'));</script>
			*/
			
			// $html .= '<div class="fb-like" data-href="' . urlencode($item->get_permalink()) . '" data-send="false" data-width="200" data-show-faces="true"></div></li>'; // Unsure what is a good width
		
			// It gets worse, in order to control what appears on a wall, you have to use meta tags specifically the description...
			// I had to build this functionality into the mashup not into the collection class!
		
         }
         
         
         
        /**
         * This method searches YouTube to try and find an appropriate video clip four the source of the quotation 
         *
         * @return string 
         */
         public function youtubeClipForCurrentQuotationSource()
         {
         	$clipHTML = NULL;
	   		
	   		if($this->isCurrentQuotationFromAFilm())
			{
				// I did a lot of expirementation trying to find the trailer for films using my YouTube search code.  It is less than 100% successful. 
				// And it really helps if you have the director.  I do have the director from IMDB...
				$imdbInfo = $this->searchIMDBForFilm($this->currentQuotationSource());
				$searchString = 'theatrical trailer for "' . $imdbInfo->Title . '" directed by ' . $imdbInfo->Director;
				$clipHTML = $this->embeddableVideoClipFor($searchString);
			}
			else if($this->isCurrentQuotationFromASong())
			{
				// We have a quotation from a song thus by a songwriter
				$searchString = '"' . $this->currentQuotationSource() . ' live"';
				$clipHTML = $this->embeddableVideoClipFor($searchString);
			}
			else
			{
				// We have a quotation by a person or ficticious person
				// This is proving very disappointing especially for the long dead
				// Try using extra keywords from Wikipedia...
				$wikiXML = $this->wikipediaInfoForCurrentQuotation();
				$searchString = $this->currentQuotationSource() . ' ' . $wikiXML->Section->Item->Description;
				$clipHTML = $this->embeddableVideoClipFor($searchString);
			}
			
			if ($clipHTML == NULL)
			{
				// This could easily happen, in which case I could display no YouTube clip...
				// However try one last time
				// The Princess Bride must be well policed in YouTube as I think that film triggers this condition among others...
				$searchString = '"' . $this->currentQuotationSource() . '" clip';
				$clipHTML = $this->embeddableVideoClipFor($searchString);
			}
			
			return $clipHTML;
         }
         
         
         
        /**
         * This method creates a fully functional "Tweet This" button.  You don't need to register an app at Twitter to just do this.
         * It uses Twitter's latest Javascript but takes in two arguments, both strings, apparently you shouldn't URL encode the URL.
         *
         * More information on Twitter buttons can be found here:
         * https://dev.twitter.com/docs/tweet-button
         *
         * @param string
         * @param string
         * @return string
         */
         public function tweetThisButton($quotation, $twitterDataURL = myInfo::MY_HOME_PAGE)
         {
         	$tweetThisButtonCode = null;
         	
         	// We should also strip the quotation passed in of junk and HTML tags...
         	$newLinesVersion = str_replace('<br />', "\n", $quotation);
         	$quotationWithNewLine = $newLinesVersion . "\n";
         	
         	if($this->isCurrentQuotationTweetable())
         	{
         		$openingTag = '<a href="http://twitter.com/share" class="twitter-share-button" data-url="' . $twitterDataURL . '" data-text="' . strip_tags($quotationWithNewLine) . '">';
         		$closingTag = '</a><script>!function(d,s,id){var js,fjs=d.getElementsByTagName(s)[0];if(!d.getElementById(id)){js=d.createElement(s);js.id=id;js.src="//platform.twitter.com/widgets.js";fjs.parentNode.insertBefore(js,fjs);}}(document,"script","twitter-wjs");</script>';
         	}
         	else
         	{
         		// Need to chomp then Tweet.
         		$excerpt = substr(strip_tags($quotationWithNewLine), 0, 100) . '...';
         		$openingTag = '<a href="http://twitter.com/share" class="twitter-share-button" data-url="' . $twitterDataURL . '" data-text="' . $excerpt . '">';
         		$closingTag = '</a><script>!function(d,s,id){var js,fjs=d.getElementsByTagName(s)[0];if(!d.getElementById(id)){js=d.createElement(s);js.id=id;js.src="//platform.twitter.com/widgets.js";fjs.parentNode.insertBefore(js,fjs);}}(document,"script","twitter-wjs");</script>';	
         	}
         	
         	// Try not encoding $twitterDataURL, seems to work this way and not the other...

         	$tweetThisButtonCode = $openingTag . "Tweet" . $closingTag;
         	
         	return $tweetThisButtonCode;
         }
         
         
         
        /**
         * This method checks to see if the passed in quotation (array) is from a film by checking the third column 
         *
         * @param array
         * @return bool 
         */
         public function isFromFilm($quotation)
         {
         	$result = false;
         	
         	if(strcmp(trim($quotation[2]), 'movie') == 0)
         	{
         		$result = true;
         	}
         	
         	
         	return $result;
         }
         
         
         
        /**
         * This is a convience method to see if the current quotation is from a film 
         *
         * @return bool
         */
        public function isCurrentQuotationFromAFilm()
        {
        	return $this->isFromFilm($this->currentMemberAsArray());
        }
        
        
        
        /**
         * This method checks to see if the passed in quotation (array) is from a song by checking the third column 
         *
         * @param array
         * @return bool 
         */
         public function isFromSong($quotation)
         {
         	$result = false;
         	
         	if(strcmp(trim($quotation[2]), 'song') == 0)
         	{
         		$result = true;
         	}
         	
         	
         	return $result;
         }
         
         
         
        /**
         * This is a convience method to see if the current quotation is from a film 
         *
         * @return bool
         */
        public function isCurrentQuotationFromASong()
        {
        	return $this->isFromSong($this->currentMemberAsArray());
        }
        
        
        
		/**
		 * This method only makes sense to call when the source of the quotation is a song or a singer, there must be a way to enforce this in PHP,
		 * but in the mean time I'll base my method on one I've already written in albumCollection.php 
		 *
		 * @param string
		 * @return string of valid HTML
		 */
		 public function lastFMBadgeForQuotationSource($quotationSource)
		 {
				$htmlTag = '#';  // was null
				
				try
				{
					$artistInfo = $this->getArtistInfoFromLastFM($quotationSource);
				}
				catch(Exception $e)
				{	
					// Passing in two artists, such as co-songwriters is causing issues, catch and set results to null...
					$artistInfo = null;
				}
				
				if($artistInfo != null)
				{
					$openLinkTag = '<a href="' . $artistInfo["url"] . '" >';
					$closeLinkTag = '</a>';
					$iconTag = '<img src="' . myInfo::LAST_FM_ICON_URL . '" class="iconImage" />';
					
					$htmlTag = $openLinkTag . $iconTag . $closeLinkTag;
				}
				
				return $htmlTag;
		 }
		 
		 
		 
		 // This method is overrided and deprechiated as I wrote a more versatile one 
		 protected function getArtistResultsFromITunes($artistName)
		 {
			throw new Exception('Please use getResultsFromITunesForSourceOfQuotation() instead');
		 }
		 
		 
		 
     	/**
     	 * This method searches the iTunes store and returns the artist page, or best guess at the product page.
     	 *
     	 * @param array
     	 * @return Simple XML object
     	 */
     	 protected function getResultsFromITunesForSourceOfQuotation($quotation)
     	 {
     	 	// This method replaces getArtistResultsFromITunes() but follows the basic technique caching the results.
     	 	
     	 	$iTunesInfo = null;
		 
			$strippedSource = $quotation[0];
			$strippedSource = preg_replace("/[^a-zA-Z0-9]/", "", $strippedSource);
			
			if(is_string($quotation[0]) && strlen($strippedSource) > 0)
			{
				$myCache = new Caching("./MashupCache/iTunes/", $strippedSource);
				
				if ($myCache->needToRenewData())
				{
					try
					{	
						// Now we will have a three or more pronged approach, just like many methods in this class 
						
						if($this->isFromFilm($quotation))
						{
							// Here we want media to be movie 
							$formattedSource = str_replace(' ', '+', $quotation[0]);  // May have to do a lot more formatting than this!
							$iTunesSearchString = 'http://ax.itunes.apple.com/WebObjects/MZStoreServices.woa/wa/wsSearch?term=' . $formattedSource . '&entity=movie&media=movie';

						}
						else if ($this->isFromSong($quotation))
						{
							// This can be the same give or take as the parent class, searching for an artist page.
							$formattedArtistString = str_replace(' ', '+', $quotation[0]);  // May have to remove other characters, but I don't in the parent...
							$iTunesSearchString = 'http://ax.itunes.apple.com/WebObjects/MZStoreServices.woa/wa/wsSearch?term=' . $formattedArtistString . '&entity=musicArtist';
						}
						else
						{
							// This is going to be less likely to return results from the iTunes store, but it has so much stuff so who knows
							// Going to go with media of type ebook here 
							$formattedSource = str_replace(' ', '+', $quotation[0]);  // May have to do a lot more formatting than this!
							$iTunesSearchString = 'http://ax.itunes.apple.com/WebObjects/MZStoreServices.woa/wa/wsSearch?term=' . $formattedSource . '&entity=ebook&media=ebook';
						}
						$searchResult = fetchThisURL($iTunesSearchString);
						$iTunesInfo = json_decode($searchResult);
					}
					catch(Exception $e)
					{	
						echo $e->getMessage();
					}				
					$serializedObject = serialize($iTunesInfo);
					
					$myCache->saveSerializedDataToFile($serializedObject);
				}
				else
				{
					// It doesn't need to be renewed so use local copy of array
					$iTunesInfo =  $myCache->getUnserializedData();
				}
			}
			else
			{
				throw new Exception('Incorrect data type passed to getResultsFromITunesForSourceOfQuotation()');
			}
			
			return $iTunesInfo;
     	 	
     	 }
     
     
     
    	/**
         * Returns a string consisting of a link and an image (icon) for the Apple iTunes store, the link goes to the 
         * artist info page or another appropriate page.  I decided to return valid HTML as I thought this
         * would save some time later on and some services it is much more involved to get the correct info and link to work.
         * Apple's iTunes Associate program isn't available in Canada but if it were, this is where you'd want to put in your associate ID
         *
         * I now cache the JSON results returned from Apple as serialized objects in the method getResultsFromITunesForSourceOfQuotation()
         *
         * @return string;
         */
         public function iTunesBadgeForCurrentQuotation()
         {
         	$finalHTML = null;
         	
         	try
         	{
         		$iTunesInfo= $this->getResultsFromITunesForSourceOfQuotation($this->currentMemberAsArray());
         	}
         	catch(Exception $e)
         	{
         		throw new Exception("Something went wrong while attempting to access iTunes data on: " . $this->currentQuotationSource());
         	}
    
			if ( ($iTunesInfo != NULL) && ($iTunesInfo->resultCount > 0))
			{	
				 // print("<pre>");
				 // print_r($iTunesInfo->results[0]);
				 // print("</pre>");
				
				if ($this->isCurrentQuotationFromAFilm())
				{
					$iTunesArtistLink = $iTunesInfo->results[0]->trackViewUrl;  
				}
				else if($this->isCurrentQuotationFromASong())
				{
					// This is the same as albumCollection 
					// This line return an error "Undefined property: stdClass::$artistLinkUrl " for Steve Earle, need to investigate why...
					$iTunesArtistLink = $iTunesInfo->results[0]->artistLinkUrl;
				}
				else
				{
					// I seem to be smarter today, than the day I gave up trying to fix this...
					$iTunesArtistLink = $iTunesInfo->results[0]->artistViewUrl; 
				}
				
				$openLinkTag = '<a href="' . $iTunesArtistLink . '" >';
				$closeLinkTag = '</a>';
				$iconTag = '<img src="' . myInfo::APPLE_ICON_URL . '" class="iconImage" />';
				$finalHTML = $openLinkTag . $iconTag . $closeLinkTag;
			}
			
			return $finalHTML;
         }
	}
?>