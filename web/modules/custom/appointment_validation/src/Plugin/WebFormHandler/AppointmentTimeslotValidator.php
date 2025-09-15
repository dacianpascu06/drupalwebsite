<?php

namespace Drupal\appointment_validation\Plugin\WebformHandler;

use Drupal\Core\Form\FormStateInterface;
use Drupal\webform\Plugin\WebformHandlerBase;
use Drupal\webform\WebformSubmissionInterface;
use Drupal\node\Entity\Node;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Validates Timeslot field based on doctor's working hours (format: 07:00-18:00).
 *
 * @WebformHandler(
 *   id = "appointment_timeslot_validator",
 *   label = @Translation("Appointment Timeslot Validator"),
 *   category = @Translation("Validation"),
 *   description = @Translation("Validates the Timeslot against the selected Doctor's working hours."),
 *   cardinality = \Drupal\webform\Plugin\WebformHandlerInterface::CARDINALITY_SINGLE,
 *   results = \Drupal\webform\Plugin\WebformHandlerInterface::RESULTS_PROCESSED,
 *   submission = \Drupal\webform\Plugin\WebformHandlerInterface::SUBMISSION_OPTIONAL,
 * )
 */
class AppointmentTimeslotValidator extends WebformHandlerBase
{
  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state, WebformSubmissionInterface $webform_submission)
  {
    $values = $form_state->getValues();
    $doctor_nid = $values['doctor'] ?? NULL;
    $timeslot = $values['timeslot'] ?? NULL;

    if (!$doctor_nid || !$timeslot) {
      return;
    }

    $doctor = Node::load($doctor_nid);
    if (!$doctor) {
      return;
    }

    $working_hours = $doctor->get('field_working_hours')->value ?? '';
    if (!$working_hours || strpos($working_hours, '-') === FALSE) {
      $form_state->setErrorByName('timeslot', $this->t('Working hours for this doctor are not configured properly.'));
      return;
    }

    list($start_time, $end_time) = explode('-', $working_hours);
    $start_seconds = $this->timeToSeconds(trim($start_time));
    $end_seconds = $this->timeToSeconds(trim($end_time));

    // seconds since midnight as it is easier to compare.
    $slot_seconds = $this->timeToSeconds(date('H:i:s', strtotime($timeslot)));

    if ($slot_seconds < $start_seconds || $slot_seconds > $end_seconds) {
      $form_state->setErrorByName('timeslot', $this->t(
        'Selected timeslot is outside the working hours of the doctor (%start - %end).',
        [
          '%start' => trim($start_time),
          '%end' => trim($end_time),
        ]
      ));
    }
  }

  function timeToSeconds(string $time): int
  {
    $parts = explode(':', $time);
    $hours = (int) ($parts[0] ?? 0);
    $minutes = (int) ($parts[1] ?? 0);
    $seconds = (int) ($parts[2] ?? 0);
    return $hours * 3600 + $minutes * 60 + $seconds;
  }
}
