import * as React from 'react';

import { useTranslation } from 'react-i18next';

import { makeStyles, Paper, Typography } from '@material-ui/core';

import { Status } from '../../../models';
import CompactStatusChip from '../CompactStatusChip';
import OutputInformation from '../OutputInformation';

import SelectableResourceName from './SelectableResourceName';

interface Props {
  name: string;
  status: Status;
  information?: string;
  subInformation?: string;
  onSelect: () => void;
}

const useStyles = makeStyles((theme) => ({
  serviceCard: {
    padding: theme.spacing(1),
  },
  serviceDetails: {
    display: 'grid',
    gridAutoFlow: 'columns',
    gridTemplateColumns: 'auto 1fr auto',
    gridGap: theme.spacing(2),
    alignItems: 'center',
  },
  description: {
    display: 'grid',
    gridAutoFlow: 'row',
    gridGap: theme.spacing(1),
  },
}));

const ServiceCard = ({
  name,
  status,
  information,
  subInformation,
  onSelect,
}: Props): JSX.Element => {
  const classes = useStyles();
  const { t } = useTranslation();

  return (
    <Paper className={classes.serviceCard}>
      <div className={classes.serviceDetails}>
        <div>
          <CompactStatusChip
            label={t(status.name)}
            severityCode={status.severity_code}
          />
        </div>
        <div className={classes.description}>
          <SelectableResourceName name={name} onSelect={onSelect} />
          <OutputInformation content={information} />
        </div>
        {subInformation && (
          <Typography variant="caption">{subInformation}</Typography>
        )}
      </div>
    </Paper>
  );
};

export default ServiceCard;
export { useStyles };
