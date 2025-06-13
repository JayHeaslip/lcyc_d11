<?php

namespace Drupal\date_ical;

use Kigkonsult\Icalcreator\Vcalendar;

/**
 * Date iCal service.
 */
class DateICal implements DateICalInterface {

  /**
   * Feed iCal.
   *
   * @param array $events
   *   Event items.
   * @param array $header
   *   iCal header Example.
   */
  public function feed(array $events, array $header = []) {
    global $base_url;
    $timezone = date_default_timezone_get();
    $dateTimeZone = new \DateTimezone($timezone);
    $vcalendar = Vcalendar::factory([Vcalendar::UNIQUE_ID => $base_url])
      ->setMethod(Vcalendar::PUBLISH);
    foreach ($header as $wr => $value) {
      if (!empty($value)) {
        $vcalendar->setXprop("x-wr-$wr", $value);
      }
    }
    foreach ($events as $row_index => $field) {
      $start = $field['date_field'];
      $end = NULL;
      if (is_array($start)) {
        $start = $field['date_field']['value'];
        $end = $field['date_field']['end_value'] ?? NULL;
        if ($end == $start) {
          $end = NULL;
        }
      }
      if (!empty($field['end_field']['value'])) {
        $end = $field['end_field']['value'];
      }
      if (is_numeric($start)) {
        $start = date('Y-m-d\TH:i:s', (int) $start);
      }
      else {
        if (strlen($start) === 10) {
          $start .= 'T00:00:00';
        }
        $date = new \DateTime($start, new \DateTimeZone('UTC'));
        $date->setTimezone($dateTimeZone);
        $start = $date->format("Y-m-d\TH:i:s");
      }
      $event = $vcalendar->newVevent()
        ->setTransp(Vcalendar::OPAQUE)
        ->setClass(Vcalendar::P_BLIC)
        ->setSequence($row_index + 1)
        ->setDtstart(new \DateTime($start, $dateTimeZone));
      if (empty($end)) {
        $end = date('Y-m-d\T23:59:59', strtotime($start));
      }
      if (!empty($end)) {
        if (is_numeric($end)) {
          $end = date('Y-m-d\TH:i:s', (int) $end);
        }
        else {
          if (strlen($end) === 10) {
            $end .= 'T23:59:59';
          }
          $date = new \DateTime($end, new \DateTimeZone('UTC'));
          $date->setTimezone($dateTimeZone);
          $end = $date->format("Y-m-d\TH:i:s");
        }
        $event->setDtend(new \DateTime($end, $dateTimeZone));
      }

      // Render description.
      if (!empty($field['description_field'])) {
        $event->setDescription((string) $field['description_field']);
      }
      // Render summary.
      if (!empty($field['summary_field'])) {
        $title = trim(strip_tags($field['summary_field']));
        $event->setSummary(preg_replace('/(\s{2,}|\r?\n)/', ' ', $title));
      }
      // Render description.
      if (!empty($field['description_field'])) {
        $description = trim(strip_tags(html_entity_decode($field['description_field']), '<a><b><u><strong><ul><ol><li><br><hr><h5><h4><h3><h2><h1><p>'));
        $event->setDescription(preg_replace('/(\s{2,})/', ' ', $description));
        // Check html add X-ALT_DESC support VCal Microsoft.
        if ($description != $field['description_field']) {
          $event->setXprop('X-ALT-DESC;FMTTYPE=text/html', preg_replace('/(\s{2,}|\r?\n)/', ' ', $field['description_field']));
        }
      }
      // Render geolocation.
      if (!empty($field['geo_field']) && is_array($field['geo_field']) && !empty($field['geo_field']['lat']) && !empty($field['geo_field']['lon'])) {
        $event->setGeo($field['geo_field']['lat'], $field['geo_field']['lon']);
      }
      // Render location.
      if (!empty($field['location_field'])) {
        $altrep = [];
        $location = $field['location_field'];
        if (is_string($location)) {
          preg_match('/<a\s*href=[\'"](.*?)[\'"][^>]+?>(.*?)<\/a>/is', $location, $matches);
          if (isset($matches[1]) && isset($matches[2])) {
            $altrep = [Vcalendar::ALTREP => $matches[1]];
            $location = $matches[2];
          }
        }
        if (is_array($field['location_field'])) {
          if (!empty($field['location_field']['url'])) {
            $altrep = [Vcalendar::ALTREP => $field['location_field']['url']];
            $location = $field['location_field']['title'] ?? '';
          }
        }
        $event->setLocation($location, $altrep);
        if (!empty($field['geo_field'])) {
          $event->setXprop('X-APPLE-STRUCTURED-LOCATION',
            'geo:' . implode(',', [
              $field['geo_field']['lat'],
              $field['geo_field']['lon'],
            ]),
            [
              'VALUE' => 'URI',
              'X-ADDRESS' => $location,
              'X-TITLE' => $location,
              'X-APPLE-RADIUS' => '14130.83822349481',
            ]
          );
        }
      }
      // Render organizer. Get only 1 organizer. It must be @mail or mail array.
      if (!empty($field['organizer_field'])) {
        $organizer = is_array($field['organizer_field']) ? current($field['organizer_field']) : $field['organizer_field'];
        if (is_string($organizer) && filter_var($organizer, FILTER_VALIDATE_EMAIL)) {
          $event->setOrganizer($organizer);
        }
        elseif (!empty($organizer['mail'])) {
          if (filter_var($organizer['mail'], FILTER_VALIDATE_EMAIL)) {
            $event->setOrganizer($organizer['mail'], [Vcalendar::CN => $organizer['name'] ?? '']);
          }
        }
      }
      // Render categories.
      if (!empty($field['categories_field'])) {
        $event->setCategories($field['categories_field']);
      }
      // Render status.
      $vEventStatus = ['CONFIRMED', 'CANCELLED', 'TENTATIVE'];
      if (!empty($field['status_field']) && in_array($field['status_field'], $vEventStatus)) {
        $event->setStatus($field['status_field']);
      }
      // Render URL.
      if (!empty($field['url_field'])) {
        preg_match('/<a\s+href="([^"]+)"/i', $field['url_field'], $matches);
        if (isset($matches[1])) {
          $field['url_field'] = $matches[1];
        }
        $event->setUrl($field['url_field']);
      }
      // Render Recurrence Rule.
      $rruleAllowed = [
        Vcalendar::SECONDLY, Vcalendar::MINUTELY, Vcalendar::HOURLY,
        Vcalendar::DAILY, Vcalendar::WEEKLY, Vcalendar::MONTHLY, Vcalendar::YEARLY,
      ];
      if (!empty($field['rrule']) && in_array(strtoupper($field['rrule']), $rruleAllowed)) {
        $rrule = [
          Vcalendar::FREQ => strtoupper($field['rrule']),
        ];
        if (!empty($field['rrule_field']) && is_numeric($field['rrule_field'])) {
          $rrule[Vcalendar::COUNT] = $field['rrule_field'];
        }
        $event->setRrule($rrule);
      }
      // Render attendees. It must be @mail or mail array.
      if (!empty($field['attendee_field'])) {
        foreach ($field['attendee_field'] as $attendee) {
          if (is_string($attendee) && filter_var($attendee, FILTER_VALIDATE_EMAIL)) {
            $event->setAttendee($attendee);
          }
          elseif (!empty($attendee['mail']) && filter_var($attendee['mail'], FILTER_VALIDATE_EMAIL)) {
            $event->setAttendee($attendee['mail'], [
              Vcalendar::ROLE => Vcalendar::REQ_PARTICIPANT,
              Vcalendar::PARTSTAT => Vcalendar::NEEDS_ACTION,
              Vcalendar::RSVP => Vcalendar::TRUE,
              Vcalendar::CN => $attendee['name' ?? ''],
            ]);
          }
        }
        unset($field['attendee_field']);
      }
      // Render attachments.
      if (!empty($field['attach_field'])) {
        if (is_array($field['attach_field'])) {
          foreach ($field['attach_field'] as $attach) {
            if (is_string($attach)) {
              $event->setAttach($attach);
            }
            if (is_array($attach)) {
              $url = array_shift($attach);
              $event->setAttach($url, $attach);
            }
          }
        }
      }
      // Render alarm.
      if (!empty($field['alarm_field'])) {
        if (is_numeric($field['alarm_field'])) {
          $field['alarm_field'] = 'PT' . $field['alarm_field'] . 'M';
        }
        $event->newValarm()
          ->setAction(Vcalendar::DISPLAY)
          ->setDescription($event->getDescription())
          ->setTrigger('-' . $field['alarm_field']);
      }
      // Render dtstamp.
      if (isset($field['exclude_dtstamp'])) {
        $event->setDtstamp($field['exclude_dtstamp']);
      }
      // Render created.
      if (isset($field['created'])) {
        $event->setCreated($field['created']);
      }
      // Render changed.
      if (isset($field['last-modified'])) {
        $event->setLastmodified($field['last-modified']);
      }
      // Render uid.
      if (isset($field['uuid'])) {
        $event->setUid($field['uuid']);
      }
    }
    $output = $vcalendar->vtimezonePopulate()->createCalendar();
    return $output;
  }

}
