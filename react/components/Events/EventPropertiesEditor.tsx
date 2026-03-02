import type * as React from "react";
import DatePicker from "react-datepicker";
import Select, { type ActionMeta, type MultiValue, type SingleValue } from "react-select";
import type Calendar from "../../interfaces/Calendar";
import type CRMEvent from "../../interfaces/CRMEvent";
import type EventLocation from "../../interfaces/EventLocation";
import type EventType from "../../interfaces/EventType";
import QuillEditor from "../QuillEditor";

interface Option {
  value: number;
  label: string;
}

const EventPropertiesEditor: React.FunctionComponent<{
  event: CRMEvent;
  calendars: Array<Calendar>;
  locations: Array<EventLocation>;
  eventTypes: Array<EventType>;
  changeHandler: (event: React.ChangeEvent<HTMLInputElement>) => void;
  handleStartDateChange: (date: Date | null) => void;
  handleEndDateChange: (date: Date | null) => void;
  pinnedCalendarChanged: (newValue: MultiValue<Option>, actionMeta: ActionMeta<Option>) => void;
  eventLocationChanged: (newValue: SingleValue<Option>, actionMeta: ActionMeta<Option>) => void;
  eventTypeChanged: (newValue: SingleValue<Option>, actionMeta: ActionMeta<Option>) => void;
}> = ({
  event,
  calendars,
  locations,
  eventTypes,
  changeHandler,
  handleStartDateChange,
  handleEndDateChange,
  pinnedCalendarChanged,
  eventLocationChanged,
  eventTypeChanged,
}) => {
  //map the Calendar data type (returned from CRM API) into something that react-select can present as dropdown choices
  const calendarOptions: Option[] = calendars.map((Pcal: Calendar) => ({
    value: Pcal.Id,
    label: Pcal.Name,
  }));
  const EventTypeOptions: Option[] = eventTypes.map((eventType: EventType) => ({
    value: eventType.Id,
    label: eventType.Name,
  }));
  const locationOptions: Option[] = locations.map((location: EventLocation) => ({
    value: location.LocationId,
    label: location.LocationName,
  }));
  const initialPinnedCalendarValue: Option[] = calendars
    .filter((Pcal: Calendar) => event.PinnedCalendars?.includes(Pcal.Id))
    .map((Pcal: Calendar) => ({ value: Pcal.Id, label: Pcal.Name }));
  const initialEventTypeValue: Option | undefined = eventTypes
    .map((eventType: EventType) => {
      if (event.Type === eventType.Id) {
        return { value: eventType.Id, label: eventType.Name };
      }
      return undefined;
    })
    .find((option) => option !== undefined);
  const initialLocationValue: Option | undefined = locationOptions.find((locationOption: Option) => {
    return event.LocationId === locationOption.value;
  });
  return (
    <table className="table w-100" style={{ tableLayout: "fixed" }}>
      <tbody>
        <tr>
          <td className="LabelColumn">{window.i18next.t("Event Type")}</td>
          <td className="TextColumn">
            <Select
              name="EventType"
              inputId="EventType"
              options={EventTypeOptions}
              value={initialEventTypeValue}
              onChange={eventTypeChanged}
            />
          </td>
        </tr>
        <tr>
          <td className="LabelColumn">{window.i18next.t("Description")}</td>
          <td className="TextColumn">
            <QuillEditor
              name="Desc"
              value={event.Desc || ""}
              onChange={(name: string, html: string) => {
                const changeEvent = {
                  target: { name, value: html },
                } as unknown as React.ChangeEvent<HTMLInputElement>;
                changeHandler(changeEvent);
              }}
            />
          </td>
        </tr>
        <tr>
          <td className="LabelColumn">{window.i18next.t("Start Date")}</td>
          <td className="TextColumn">
            <DatePicker
              selected={event.Start || null}
              onChange={handleStartDateChange}
              showTimeSelect
              timeIntervals={15}
              dateFormat="MMMM d, yyyy h:mm aa"
              timeCaption="time"
              className="form-control w-100"
              placeholderText={window.i18next.t("Start Date")}
              autoComplete="off"
              wrapperClassName="datepicker-wrapper w-100"
              popperClassName="react-datepicker-popper-wide"
            />
          </td>
        </tr>
        <tr>
          <td className="LabelColumn">{window.i18next.t("End Date")}</td>
          <td className="TextColumn">
            <DatePicker
              selected={event.End || null}
              onChange={handleEndDateChange}
              showTimeSelect
              timeIntervals={15}
              dateFormat="MMMM d, yyyy h:mm aa"
              timeCaption="time"
              className="form-control w-100"
              placeholderText={window.i18next.t("End Date")}
              autoComplete="off"
              wrapperClassName="datepicker-wrapper w-100"
              popperClassName="react-datepicker-popper-wide"
            />
          </td>
        </tr>
        <tr>
          <td className="LabelColumn">{window.i18next.t("Pinned Calendars")}</td>
          <td className="TextColumn">
            <Select
              name="PinnedCalendars"
              inputId="PinnedCalendars"
              options={calendarOptions}
              value={initialPinnedCalendarValue}
              onChange={pinnedCalendarChanged}
              isMulti={true}
            />
            {event.PinnedCalendars && event.PinnedCalendars.length === 0 && (
              <div className="text-danger small mt-2">
                <i className="fas fa-exclamation-circle me-1"></i>
                {window.i18next.t("This field is required")}
              </div>
            )}
          </td>
        </tr>
        <tr>
          <td className="LabelColumn">{window.i18next.t("Location")}</td>
          <td className="TextColumn">
            <Select
              name="LocationId"
              inputId="LocationId"
              options={locationOptions}
              value={initialLocationValue}
              onChange={eventLocationChanged}
              isClearable={true}
            />
          </td>
        </tr>
        <tr>
          <td className="LabelColumn">{window.i18next.t("Address")}</td>
          <td className="TextColumn">
            <input
              name="LocationText"
              id="LocationText"
              className="form-control"
              maxLength={255}
              value={event.LocationText || ""}
              onChange={changeHandler}
              placeholder={window.i18next.t("Street, Postal Code, City, Country")}
            />
          </td>
        </tr>
        <tr>
          <td className="LabelColumn">{window.i18next.t("Text")}</td>
          <td className="TextColumn">
            <QuillEditor
              name="Text"
              value={event.Text || ""}
              onChange={(name: string, html: string) => {
                const changeEvent = {
                  target: { name, value: html },
                } as unknown as React.ChangeEvent<HTMLInputElement>;
                changeHandler(changeEvent);
              }}
              minHeight="300px"
            />
          </td>
        </tr>
      </tbody>
    </table>
  );
};

export default EventPropertiesEditor;
