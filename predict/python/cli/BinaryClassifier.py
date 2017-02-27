import os
import math
import logging
import resource

import numpy as np

from sklearn.cross_validation import train_test_split
from sklearn.linear_model import LogisticRegression
from sklearn.metrics import roc_curve, auc
from sklearn.linear_model import LogisticRegressionCV
from sklearn.externals import joblib

from Classifier import Classifier
from RocCurve import RocCurve
from LearningCurve import LearningCurve

class BinaryClassifier(Classifier):

    def __init__(self, modelid, directory, log_into_file=True):

        super(BinaryClassifier, self).__init__(modelid, directory, log_into_file)

        self.aucs = []
        self.classes = [1, 0]


    def train(self, X_train, y_train, classifier=False):

        if classifier == False:
            # Init the classifier.
            classifier = self.get_classifier(X_train, y_train)

        # Fit the training set. y should be an array-like.
        classifier.fit(X_train, y_train[:,0])

        # Returns the trained classifier.
        return classifier


    def train_dataset(self, filepath):
        # TODO Move this to Classifier and make it multiple classes compatible.

        [self.X, self.y] = self.get_labelled_samples(filepath)

        # Load the loaded model if it exists.
        classifier_filepath = os.path.join(self.persistencedir, Classifier.PERSIST_FILENAME)
        if os.path.isfile(classifier_filepath) == True:
            classifier = joblib.load(classifier_filepath)
        else:
            # Not previously trained.
            classifier = False

        trained_classifier = self.train(self.X, self.y, classifier)

        joblib.dump(trained_classifier, classifier_filepath)

        result = dict()
        result['status'] = Classifier.OK
        result['errors'] = []
        return result


    def predict_dataset(self, filepath):
        # TODO Move this to Classifier and make it multiple classes compatible.

        [sampleids, x] = self.get_unlabelled_samples(filepath)

        classifier_filepath = os.path.join(self.persistencedir, Classifier.PERSIST_FILENAME)
        if os.path.isfile(classifier_filepath) == False:
            result = dict()
            result['status'] = Classifier.NO_DATASET
            result['errors'] = ['Provided model have not been trained yet']
            return result

        classifier = joblib.load(classifier_filepath)

        # Prediction and calculated probability of each of the labels.
        y_proba = classifier.predict_proba(x)
        y_pred = classifier.predict(x)
        # Probabilities of the predicted response being correct.
        probabilities = y_proba[range(len(y_proba)), y_pred]

        result = dict()
        result['status'] = Classifier.OK
        result['errors'] = []
        # First column sampleids, second the prediction and third how reliable is the prediction (from 0 to 1).
        result['predictions'] = np.vstack((sampleids[:,0], y_pred, probabilities)).T.tolist()

        return result


    def evaluate_dataset(self, filepath, min_score=0.6, accepted_deviation=0.02, n_test_runs=100):
        # TODO Move this to Classifier and make it multiple classes compatible.

        [self.X, self.y] = self.get_labelled_samples(filepath)

        # Classes balance check.
        counts = []
        y_array = np.array(self.y.T[0])
        counts.append(np.count_nonzero(y_array))
        counts.append(len(y_array) - np.count_nonzero(y_array))
        logging.info('Number of samples by y value: %s' % str(counts))
        balanced_classes = self.check_classes_balance(counts)
        if balanced_classes != False:
            logging.warning(balanced_classes)

        # ROC curve.
        self.roc_curve_plot = RocCurve(self.logsdir, 2)

        # Learning curve.
        if self.log_into_file == True:
            try:
                self.store_learning_curve()
            except ValueError:
                # The learning curve cross-validation process may trigger a
                # ValueError if all examples in a data chunk belong to the same
                # class, which is likely to happen when the number of samples
                # is small.
                logging.info('Learning curve generation skipped, not enough samples')

        for i in range(0, n_test_runs):

            # Split samples into training set and test set (80% - 20%)
            X_train, X_test, y_train, y_test = train_test_split(self.X, self.y, test_size=0.2)

            classifier = self.train(X_train, y_train)

            self.rate_prediction(classifier, X_test, y_test)

        # Store the roc curve.
        if self.log_into_file == True:
            fig_filepath = self.roc_curve_plot.store()
            logging.info("Figure stored in " + fig_filepath)

        # Return results.
        result = self.get_evaluation_results(min_score, accepted_deviation)

        # Add the run id to identify it in the caller.
        result['runid'] = int(self.get_runid())

        logging.info("Accuracy: %.2f%%" % (result['accuracy'] * 100))
        logging.info("Precision (predicted elements that are real): %.2f%%" % (result['precision'] * 100))
        logging.info("Recall (real elements that are predicted): %.2f%%" % (result['recall'] * 100))
        logging.info("Score: %.2f%%" % (result['score'] * 100))
        logging.info("AUC standard desviation: %.4f" % (result['auc_deviation']))

        return result


    def rate_prediction(self, classifier, X_test, y_test):

        # Calculate scores.
        y_score = self.get_score(classifier, X_test, y_test[:,0])
        y_pred = classifier.predict(X_test)

        # Transform it to an array.
        y_test = y_test.T[0]

        # Calculate accuracy, sensitivity and specificity.
        [acc, prec, rec, ph] = self.calculate_metrics(y_test == 1, y_pred == 1)
        self.accuracies.append(acc)
        self.precisions.append(prec)
        self.recalls.append(rec)
        self.phis.append(ph)

        # ROC curve calculations.
        fpr, tpr, _ = roc_curve(y_test, y_score)

        # When the amount of samples is small we can randomly end up having just
        # one class instead of examples of each, which triggers a "UndefinedMetricWarning:
        # No negative samples in y_true, false positive value should be meaningless"
        # and returning NaN.
        if math.isnan(fpr[0]) or math.isnan(tpr[0]):
            return

        self.aucs.append(auc(fpr, tpr))

        # Draw it.
        self.roc_curve_plot.add(fpr, tpr, 'Positives')


    def get_score(self, classifier, X_test, y_test):

        probs = classifier.predict_proba(X_test)

        n_samples = len(y_test)

        # Calculated probabilities of the correct response.
        return probs[range(n_samples), y_test]


    def store_model(self):
        # Train the model again now with all the dataset and store the results.
        classifier = self.train(self.X, self.y)


    def calculate_metrics(self, y_test_true, y_pred_true):

        test_p = y_test_true
        test_n = np.invert(test_p)

        pred_p = y_pred_true
        pred_n = np.invert(pred_p)

        pp = np.count_nonzero(test_p)
        nn = np.count_nonzero(test_n)
        tp = np.count_nonzero(test_p * pred_p)
        tn = np.count_nonzero(test_n * pred_n)
        fn = np.count_nonzero(test_p * pred_n)
        fp = np.count_nonzero(test_n * pred_p)

        accuracy = (tp + tn) / float(pp + nn)
        if tp != 0 or fp != 0:
            precision = tp / float(tp + fp)
        else:
            precision = 0
        if tp != 0 or fn != 0:
            recall = tp / float(tp + fn)
        else:
            recall = 0

        denominator = (tp + fp) * (tp + fn) * (tn + fp) * (tn + fn)
        if denominator != 0:
            phi = ( ( tp * tn) - (fp * fn) ) / math.sqrt(denominator)
        else:
            phi = 0

        return [accuracy, precision, recall, phi]


    def get_evaluation_results(self, min_score, accepted_deviation):

        avg_accuracy = np.mean(self.accuracies)
        avg_precision = np.mean(self.precisions)
        avg_recall = np.mean(self.recalls)
        avg_aucs = np.mean(self.aucs)
        avg_phi = np.mean(self.phis)

        # Phi goes from -1 to 1 we need to transform it to a value between
        # 0 and 1 to compare it with the minimum score provided.
        score = (avg_phi + 1) / 2

        result = dict()
        result['auc'] = avg_aucs
        result['accuracy'] = avg_accuracy
        result['precision'] = avg_precision
        result['recall'] = avg_recall
        result['auc_deviation'] = np.std(self.aucs)
        result['score'] = score
        result['min_score'] = min_score
        result['accepted_deviation'] = accepted_deviation

        result['status'] = Classifier.OK
        result['errors'] = []

        # If deviation is too high we may need more records to report if
        # this model is reliable or not.
        auc_deviation = np.std(self.aucs)
        if auc_deviation > accepted_deviation:
            result['errors'].append('The results obtained varied too much,'
                + ' we need more samples to check if this model is valid.'
                + ' Model deviation = %f, accepted deviation = %f' \
                % (auc_deviation, accepted_deviation))
            result['status'] = Classifier.EVALUATE_NOT_ENOUGH_DATA

        if score < min_score:
            result['errors'].append('The model is not good enough. Model score ='
                + ' %f, minimum score = %f' \
                % (score, min_score))
            result['status'] = Classifier.EVALUATE_LOW_SCORE

        if auc_deviation > accepted_deviation and score < min_score:
            result['status'] = Classifier.EVALUATE_LOW_SCORE + Classifier.EVALUATE_NOT_ENOUGH_DATA

        return result


    def store_learning_curve(self):
        lc = LearningCurve(self.logsdir)
        lc.set_classifier(self.get_classifier(self.X, self.y))
        lc_filepath = lc.save(self.X, self.y)
        logging.info("Figure stored in " + lc_filepath)


    def get_classifier(self, X, y):

        solver = 'liblinear'
        multi_class = 'ovr'

        if hasattr(self, 'C') == False:

            # Cross validation - to select the best constants.
            lgcv = LogisticRegressionCV(solver=solver, multi_class=multi_class);
            lgcv.fit(X, y[:,0])

            if len(lgcv.C_) == 1:
                self.C = lgcv.C_[0]
            else:
                # Chose the best C = the class with more samples.
                # Ideally multiclass problems will be multinomial.
                [values, counts] = np.unique(y[:,0], return_counts=True)
                self.C = lgcv.C_[np.argmax(counts)]
                logging.info('From all classes best C values (%s), %f has been selected' % (str(lgcv.C_), C))
            print("Best C: %f" % (self.C))

        return LogisticRegression(solver=solver, tol=1e-1, C=self.C)


    def reset_rates(self):
        super(BinaryClassifier, self).reset_rates()
        self.aucs = []

        # ROC curve.
        self.roc_curve_plot = RocCurve(self.logsdir, 2)
