<?php

namespace xorik\YtUpload\Model;

enum YoutubeCategory: int
{
    case CARS_AND_VEHICLES = 2;
    case COMEDY = 23;
    case EDUCATION = 27;
    case ENTERTAINMENT = 24;
    case FILM_AND_ANIMATION = 1;
    case GAMING = 20;
    case HOW_TO_AND_STYLE = 26;
    case MUSIC = 10;
    case NEWS_AND_POLITICS = 25;
    case NON_PROFITS_AND_ACTIVISM = 29;
    case PEOPLE_AND_BLOGS = 22;
    case PETS_AND_ANIMALS = 15;
    case SCIENCE_AND_TECHNOLOGY = 28;
    case SPORT = 17;
    case TRAVEL_AND_EVENTS = 19;

    private const CATEGORIES = [
         'Cars & Vehicles' => self::CARS_AND_VEHICLES,
         'Comedy' => self::COMEDY,
         'Education' => self::EDUCATION,
         'Entertainment' => self::ENTERTAINMENT,
         'Film & Animation' => self::FILM_AND_ANIMATION,
         'Gaming' => self::GAMING,
         'How-to & Style' => self::HOW_TO_AND_STYLE,
         'Music' => self::MUSIC,
         'News & Politics' => self::NEWS_AND_POLITICS,
         'Non-profits & Activism' => self::NON_PROFITS_AND_ACTIVISM,
         'People & Blogs' => self::PEOPLE_AND_BLOGS,
         'Pets & Animals' => self::PETS_AND_ANIMALS,
         'Science & Technology' => self::SCIENCE_AND_TECHNOLOGY,
         'Sport' => self::SPORT,
         'Travel & Events' => self::TRAVEL_AND_EVENTS,
    ];

    public function toString(): string
    {
        return array_search($this, self::CATEGORIES);
    }

    public static function fromString(string $category): self
    {
        return self::CATEGORIES[$category];
    }

    public static function getStringValues(): array
    {
        return array_keys(self::CATEGORIES);
    }
}
